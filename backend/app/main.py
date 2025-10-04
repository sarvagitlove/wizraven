from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import List, Optional, Dict
from .agents.parser_agent import ParserAgent
from .agents.analyzer_agent import AnalyzerAgent
from .agents.knowledge_agent import KnowledgeAgent
from .agents.crawler_agent import CrawlerAgent
from fastapi import Header, Request

app = FastAPI(
    title="Wizraven API",
    description="AI-powered network log analysis API",
    version="0.1.0"
)

# Configure CORS
# For local development make CORS permissive to avoid origin/port mismatches while debugging
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Initialize agents
parser_agent = ParserAgent()
analyzer_agent = AnalyzerAgent()
knowledge_agent = KnowledgeAgent()
crawler_agent = CrawlerAgent()

class Message(BaseModel):
    content: str
    context: Optional[Dict] = None

class AgentResponse(BaseModel):
    agent_type: str
    content: str
    metadata: Optional[Dict] = None


class KBAddRequest(BaseModel):
    text: str


class KBSearchRequest(BaseModel):
    query: str
    k: Optional[int] = 3

@app.get("/")
async def root():
    return {"message": "Hello Wizraven!"}

@app.post("/api/analyze")
async def analyze_logs(message: Message, request: Request, x_cerebras_api_key: Optional[str] = Header(None)):
    """Analyzer-only endpoint for the Cerebras-only MVP.

    Body: { "text": "...", "context": [...] }
    Header: X-Cerebras-Api-Key
    """
    if not message or not message.content or not message.content.strip():
        raise HTTPException(status_code=400, detail='Missing required field: text')

    # Debug: log whether header or context provided an API key (mask value)
    try:
        header_present = bool(x_cerebras_api_key)
        ctx_key = None
        if message.context and isinstance(message.context, dict):
            ctx_key = message.context.get('cerebras_api_key') or message.context.get('api_key')
        # Mask key for logs
        def _mask(k: Optional[str]) -> Optional[str]:
            if not k:
                return None
            if len(k) <= 6:
                return '***'
            return k[:3] + '...' + k[-3:]
        print(f"[DEBUG] X-Cerebras-Api-Key header present: {header_present}, header_mask={_mask(x_cerebras_api_key)}, context_has_key={bool(ctx_key)}, context_key_mask={_mask(ctx_key)}")

    except Exception:
        pass

    # API key is optional - if not provided, use a fallback or demo mode
    if not x_cerebras_api_key:
        print("[INFO] No API key provided, continuing with demo/fallback mode")

    try:
        context = message.context if isinstance(message.context, list) else message.context or []
        # Call the AnalyzerAgent's mixed input handler
        out = await analyzer_agent.analyze_mixed_input(message.content, api_key=x_cerebras_api_key, context=context)
        return out
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/api/query")
async def query_knowledge_base(message: Message, request: Request, x_cerebras_api_key: Optional[str] = Header(None)) -> List[AgentResponse]:
    try:
        if message.context is None:
            message.context = {}
        if x_cerebras_api_key:
            message.context['cerebras_api_key'] = x_cerebras_api_key

        # Query the knowledge base for relevant information (pass context)
        kb_results = await knowledge_agent.query_knowledge_base(message.content, context=message.context)

        kb_content = f"Found {len(kb_results)} relevant documents." if kb_results else "No relevant documents found."
        responses = [
            AgentResponse(
                agent_type="knowledge",
                content=kb_content,
                metadata={"documents": kb_results}
            )
        ]

        return responses

    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post('/api/kb/add')
async def kb_add(req: KBAddRequest, request: Request, x_cerebras_api_key: Optional[str] = Header(None)):
    """Add a text to the knowledge base using the provided Cerebras API key."""
    try:
        api_key = x_cerebras_api_key
        if not api_key:
            print("[INFO] No API key provided for KB add, using demo mode")
            api_key = "demo-key"

        res = await knowledge_agent.add_to_kb(req.text, api_key=api_key)
        return res
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post('/api/kb/search')
async def kb_search(req: KBSearchRequest, request: Request, x_cerebras_api_key: Optional[str] = Header(None)):
    """Search the knowledge base for a query using Cerebras embeddings and FAISS."""
    try:
        api_key = x_cerebras_api_key
        if not api_key:
            print("[INFO] No API key provided for KB search, using demo mode")
            api_key = "demo-key"

        results = await knowledge_agent.search_kb(req.query, api_key=api_key, k=req.k or 3)
        return {"results": results}
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/api/analyze/interactive")
async def interactive_analysis(message: Message, request: Request, x_cerebras_api_key: Optional[str] = Header(None)) -> List[AgentResponse]:
    """
    Handle interactive analysis requests with context-aware responses.
    """
    try:
        responses = []
        # If the frontend passed the Cerebras key, attach it to context
        if message.context is None:
            message.context = {}
        if x_cerebras_api_key:
            message.context['cerebras_api_key'] = x_cerebras_api_key
        context = message.context or {}

        # If this is a follow-up question
        if context.get("conversation_id"):
            # Query knowledge base first
            kb_results = await knowledge_agent.query_knowledge_base(
                message.content, 
                context=context
            )
            
            if kb_results:
                kb_content = f"Found {len(kb_results)} relevant documents."
                responses.append(
                    AgentResponse(
                        agent_type="knowledge",
                        content=kb_content,
                        metadata={"documents": kb_results}
                    )
                )

            # If it's a technical question, get analyzer's input
            if any(keyword in message.content.lower() 
                  for keyword in ["why", "how", "what's causing", "debug"]):
                analysis = await analyzer_agent.analyze_logs(
                    context.get("parsed_data", {}),
                    focus_query=message.content,
                    request_context=context
                )
                
                analyzer_content = analysis.get('summary', "Here's my analysis of the logs.")
                responses.append(
                    AgentResponse(
                        agent_type="analyzer",
                        content=analyzer_content,
                        metadata=analysis
                    )
                )

        # If this is new log content
        elif len(message.content.split('\n')) > 1:  # Heuristic for log content
            # Start with parsing
            parsed_data = await parser_agent.process_logs(message.content, context=context)
            parser_content = f"Parsed {len(parsed_data.get('message', []))} messages. Patterns: {', '.join(parsed_data.get('patterns', [])) or 'none'}."
            responses.append(
                AgentResponse(
                    agent_type="parser",
                    content=parser_content,
                    metadata=parsed_data
                )
            )

            # Query knowledge base for related documents (if API key present)
            kb_results = await knowledge_agent.query_knowledge_base(message.content, context=context)
            if kb_results:
                kb_content = f"Found {len(kb_results)} relevant documents."
                responses.append(
                    AgentResponse(
                        agent_type="knowledge",
                        content=kb_content,
                        metadata={"documents": kb_results}
                    )
                )

            # Then analyze
            analysis = await analyzer_agent.analyze_logs(parsed_data, request_context=context)
            analyzer_content = analysis.get('summary', "Here's my analysis of the logs.")
            responses.append(
                AgentResponse(
                    agent_type="analyzer",
                    content=analyzer_content,
                    metadata=analysis
                )
            )

        return responses

    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))