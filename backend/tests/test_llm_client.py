import asyncio
import pytest

from app.utils.llm_client import LLMClient


class DummyLLM:
    def __call__(self, prompt: str) -> str:
        # simple echo-like predictable response for testing
        return "SUMMARY: Found 1 issue. FINDINGS: Example finding. RECOMMENDATIONS: Check interface."


@pytest.mark.asyncio
async def test_analyze_text_with_injected_llm():
    llm = LLMClient(llm=DummyLLM())
    out = await llm.analyze_text('test logs')
    assert 'SUMMARY:' in out


@pytest.mark.asyncio
async def test_analyze_text_without_key_raises():
    llm = LLMClient(api_key=None, llm=None)
    with pytest.raises(ValueError):
        await llm.analyze_text('test')
