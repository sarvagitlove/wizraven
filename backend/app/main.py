from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

app = FastAPI(
    title="Wizraven API",
    description="AI-powered network log analysis API",
    version="0.1.0"
)

# Configure CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:3000"],  # Frontend URL
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/")
async def root():
    return {"message": "Hello Wizraven!"}

@app.post("/api/analyze")
async def analyze_logs(request: dict):
    # TODO: Implement log analysis pipeline
    return {"analysisId": "demo", "status": "processing"}