Cerebras & LLM Provider notes

This backend supports multiple LLM providers via `LLMClient`.

To prefer Cerebras.ai as the provider set either:

- Environment variable: `WIZRAVEN_LLM_PROVIDER=cerebras`
- Or provide a Cerebras API key in `CEREBRAS_API_KEY` (the code will try to detect this)

Example startup (zsh):

```bash
export WIZRAVEN_LLM_PROVIDER=cerebras
export CEREBRAS_API_KEY="<your-key-here>"
PYTHONPATH=/Users/sarva/wizraven/backend /Users/sarva/wizraven/backend/venv/bin/python3 -m uvicorn app.main:app --reload
```

Notes:
- The Cerebras SDK may expose different client methods. The code includes a thin shim in `app/utils/llm_client.py` that attempts to call a `Client.generate` or `cerebras.generate_text` function. You may need to adapt the wrapper to match the exact SDK version.
- If Cerebras isn't installed, the client will fall back to LangChain/Google or `google.generativeai` as before.
