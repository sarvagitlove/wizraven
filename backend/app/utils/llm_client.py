import os
import asyncio
from typing import Dict, Any, Optional

try:
    from dotenv import load_dotenv
    load_dotenv()
except Exception:
    # dotenv not installed or .env not present — proceed without failing
    pass


class LLMClient:
    """Cerebras AI LLM client wrapper.

    Behavior:
    - Accepts an explicit ``llm`` object (callable) for tests or custom wiring.
    - If ``llm`` is not provided but an ``api_key`` (or CEREBRAS_API_KEY env var)
      is available, it attempts to construct a Cerebras LLM client.
    - The main method ``analyze_text`` is async but will run sync LLM calls in
      a threadpool so callers can await consistently.
    """

    def __init__(self, api_key: Optional[str] = None, llm: Optional[Any] = None):
        self.api_key = api_key or os.getenv('CEREBRAS_API_KEY')
        # allow injecting a test double or a pre-configured Cerebras LLM
        self._llm = llm

    def _ensure_llm(self):
        """Ensure a working LLM is available; try to construct one via Cerebras SDK.

        This is lazy and non-fatal: if construction fails we keep _llm==None and
        analyze_text will raise a helpful error.
        """
        if self._llm is not None:
            return

        if not self.api_key:
            # nothing we can do here
            print("[LLMClient DEBUG] No API key available to construct LLM")
            return

        # helper to mask api key for logging
        def _mask(k: str) -> str:
            if not k:
                return 'None'
            if len(k) <= 6:
                return '***'
            return k[:3] + '...' + k[-3:]

        print(f"[LLMClient DEBUG] Attempting to construct Cerebras LLM with api_key={_mask(self.api_key)})")

        # Try to create a Cerebras LLM client
        try:
            # Try common package names for Cerebras SDK
            try:
                from cerebras.cloud.sdk import Cerebras
            except ImportError:
                try:
                    from cerebras_cloud_sdk import Cerebras
                except ImportError:
                    raise ImportError("Cerebras SDK not found. Please install with: pip install cerebras-cloud-sdk")

            class _CerebrasWrapper:
                def __init__(self, api_key: str):
                    self.client = Cerebras(api_key=api_key)
                    # Set a default model - you can make this configurable
                    self.model = os.getenv('CEREBRAS_MODEL', 'llama3.1-8b')

                def __call__(self, prompt: str) -> str:
                    try:
                        response = self.client.chat.completions.create(
                            model=self.model,
                            messages=[
                                {"role": "user", "content": prompt}
                            ],
                            max_tokens=1000,
                            temperature=0.7
                        )
                        return response.choices[0].message.content
                    except Exception as e:
                        raise RuntimeError(f'Cerebras API call failed: {e}')

            self._llm = _CerebrasWrapper(self.api_key)
            print('[LLMClient DEBUG] Constructed Cerebras wrapper successfully')
            return
        except Exception as e:
            print(f"[LLMClient DEBUG] Cerebras construct failed: {e}")
            # couldn't construct any LLM; leave _llm as None
            self._llm = None

    async def analyze_text(self, text: str, context: Dict = None) -> str:
        """Analyze text using the configured LLM.

        Returns the LLM string output.
        """
        # ensure we have an LLM to call
        self._ensure_llm()
        if not self._llm:
            print("[LLMClient DEBUG] No _llm available after _ensure_llm()")
            raise ValueError('LLM client is not configured with an API key or an injectable llm')

        prompt = self._build_prompt(text, context)

        # LangChain LLMs are typically sync callables (llm(prompt)) while some
        # newer implementations may be async. Normalize to an async call by
        # running sync calls in a threadpool.
        try:
            if asyncio.iscoroutinefunction(self._llm.__call__):
                return await self._llm(prompt)

            loop = asyncio.get_event_loop()
            result = await loop.run_in_executor(None, lambda: self._llm(prompt))
            # many langchain LLMs return a string directly
            return result
        except Exception as e:
            print(f"[LLMClient DEBUG] Exception while calling _llm: {e}")
            raise

    def _build_prompt(self, text: str, context: Dict = None) -> str:
        # basic prompt; keep it simple and deterministic so tests can assert
        return f"""Analyze the following network log data:

{text}

Provide a short summary followed by findings and recommendations."""