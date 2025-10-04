from typing import Dict, List, Optional, Any
from .base_agent import Agent, Message
import numpy as np

# Defer optional heavy imports (langchain Google embeddings, faiss) until runtime
# so the app can start even if those packages are not installed. If KB methods
# are used without the packages installed, clear runtime errors will be raised.
GoogleGenerativeAIEmbeddings = None
faiss = None


class KnowledgeAgent(Agent):
    """MVP KnowledgeAgent that always uses Cerebras embeddings via LangChain
    (langchain_google_genai.GoogleGenerativeAIEmbeddings) and FAISS.

    API key must be provided per call (do not use environment variables).
    """

    def __init__(self):
        super().__init__(name="knowledge_agent",
                         system_message="""Manage an in-memory FAISS-backed KB of log text using Cerebras embeddings.""")

        # Store raw documents in a list; FAISS index will store vectors in same order
        self.documents: List[str] = []
        self.index = None
        self.dim: Optional[int] = None

    def _require_key(self, api_key: Optional[str]):
        if not api_key:
            raise ValueError('Cerebras API key is required for KnowledgeAgent operations')

    def _make_embeddings(self, api_key: str):
        # Import lazily to avoid import-time failures when the optional
        # langchain_google_genai package isn't installed in the environment.
        global GoogleGenerativeAIEmbeddings
        if GoogleGenerativeAIEmbeddings is None:
            try:
                from langchain_google_genai import GoogleGenerativeAIEmbeddings as _GGAE  # type: ignore
                GoogleGenerativeAIEmbeddings = _GGAE
            except Exception as e:
                raise ValueError('langchain_google_genai is required for KnowledgeAgent but is not installed') from e

        try:
            return GoogleGenerativeAIEmbeddings(api_key=api_key)  # type: ignore
        except Exception as e:
            # Re-raise as ValueError to keep the contract simple
            raise ValueError(f'Unable to construct Cerebras embeddings client: {e}')

    async def add_to_kb(self, log_text: str, api_key: str) -> Dict[str, Any]:
        """Embed `log_text` using Cerebras embeddings and add to FAISS index.

        Returns: {'ok': True, 'id': int}
        Raises ValueError if api_key missing/invalid.
        """
        self._require_key(api_key)
        if not log_text:
            raise ValueError('log_text must be non-empty')

        emb_client = self._make_embeddings(api_key)
        try:
            vecs = emb_client.embed_documents([log_text])
        except Exception as e:
            raise ValueError(f'Embedding failure: {e}')

        vec = np.array(vecs[0], dtype=np.float32)
        # Initialize FAISS index lazily with detected dimension
        if self.index is None:
            # Lazy import faiss to avoid requiring it at app import time
            global faiss
            if faiss is None:
                try:
                    import faiss as _faiss  # type: ignore
                    faiss = _faiss
                except Exception as e:
                    raise ValueError('faiss is required for KnowledgeAgent but is not installed') from e

            self.dim = vec.shape[0]
            self.index = faiss.IndexFlatL2(self.dim)

        if vec.shape[0] != self.dim:
            raise ValueError(f'Embedding dimension mismatch (expected {self.dim}, got {vec.shape[0]})')

        # Add to index and store document
        self.index.add(vec.reshape(1, -1))
        doc_id = len(self.documents)
        self.documents.append(log_text)

        return {"ok": True, "id": doc_id}

    async def search_kb(self, query: str, api_key: str, k: int = 3) -> str:
        """Embed `query` using Cerebras embeddings and return top-k document texts as a single string.

        Raises ValueError if api_key missing/invalid.
        """
        self._require_key(api_key)
        if self.index is None or len(self.documents) == 0:
            return ''

        emb_client = self._make_embeddings(api_key)
        try:
            qvec = np.array(emb_client.embed_query(query), dtype=np.float32)
        except Exception as e:
            raise ValueError(f'Embedding failure: {e}')

        if qvec.shape[0] != self.dim:
            raise ValueError('Query embedding dimension does not match index')

        D, I = self.index.search(qvec.reshape(1, -1), min(k, len(self.documents)))
        texts = []
        for idx in I[0]:
            if idx < 0 or idx >= len(self.documents):
                continue
            texts.append(self.documents[idx])

        return '\n\n'.join(texts)

    async def process_message(self, message: Message) -> None:
        """Simple handler: commands 'ADD: <text>' and 'SEARCH: <query>' using message.context['cerebras_api_key']."""
        try:
            api_key = (message.context or {}).get('cerebras_api_key')
            text = (message.content or '').strip()

            if text.upper().startswith('ADD:'):
                payload = text[4:].strip()
                res = await self.add_to_kb(payload, api_key=api_key)
                await self.send_message(f'Added to KB id={res["id"]}')
                return

            if text.upper().startswith('SEARCH:'):
                query = text[7:].strip()
                results = await self.search_kb(query, api_key=api_key)
                await self.send_message(results or 'No results found')
                return

            await self.send_message("Unknown command. Use 'ADD: ...' or 'SEARCH: ...'")

        except Exception as e:
            await self.send_message(f'Error processing message: {e}')

    async def query_knowledge_base(self, query: str, context: Optional[Dict[str, Any]] = None, k: int = 3) -> List[str]:
        """Helper for other parts of the app: extract API key from context and
        return a list of matching documents (may be empty). This returns an empty
        list when the KB is empty or when no API key is provided.
        """
        api_key = (context or {}).get('cerebras_api_key')
        # If no api_key present, we can't call the embeddings provider — return empty
        if not api_key:
            return []

        try:
            text = await self.search_kb(query, api_key=api_key, k=k)
        except Exception:
            # On any embedding/index error, surface no results rather than raise
            return []

        if not text:
            return []

        # search_kb returns documents joined by double-newline; split back to list
        docs = [d for d in text.split('\n\n') if d.strip()]
        return docs