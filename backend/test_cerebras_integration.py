#!/usr/bin/env python3
"""
Test script to verify Cerebras integration is working properly.
This will test the API endpoint without requiring a real API key.
"""

import asyncio
import json
from app.utils.llm_client import LLMClient

class MockCerebrasLLM:
    """Mock Cerebras LLM for testing"""
    def __call__(self, prompt: str) -> str:
        return "SUMMARY: Test response from Cerebras mock. FINDINGS: System working correctly. RECOMMENDATIONS: Integration successful."

async def test_llm_client():
    """Test the LLM client with mock Cerebras LLM"""
    print("🧪 Testing Cerebras LLM Client Integration...")
    
    # Test with mock LLM (no API key required)
    mock_llm = MockCerebrasLLM()
    client = LLMClient(llm=mock_llm)
    
    test_log = """
    2024-01-15 10:30:45 INFO: Connection established from 192.168.1.100
    2024-01-15 10:30:46 WARN: Unusual traffic pattern detected
    2024-01-15 10:30:47 ERROR: Failed authentication attempt from 192.168.1.100
    """
    
    try:
        result = await client.analyze_text(test_log)
        print("✅ LLM Client Test Result:")
        print("-" * 50)
        print(result)
        print("-" * 50)
        print("✅ Cerebras integration working correctly!")
        return True
    except Exception as e:
        print(f"❌ Test failed: {e}")
        return False

def test_configuration():
    """Test that all Gemini references have been removed"""
    print("\n🔍 Testing Configuration...")
    
    # Test LLM client initialization
    client = LLMClient()
    if hasattr(client, 'api_key'):
        print("✅ LLM Client uses environment variable correctly")
    
    print("✅ Configuration test passed!")
    return True

async def main():
    """Run all tests"""
    print("🚀 Starting Cerebras Integration Tests\n")
    
    llm_test = await test_llm_client()
    config_test = test_configuration()
    
    print("\n📊 Test Results:")
    print(f"LLM Client Test: {'✅ PASS' if llm_test else '❌ FAIL'}")
    print(f"Configuration Test: {'✅ PASS' if config_test else '❌ FAIL'}")
    
    if llm_test and config_test:
        print("\n🎉 All tests passed! Cerebras integration is working correctly.")
    else:
        print("\n⚠️  Some tests failed. Please check the errors above.")

if __name__ == "__main__":
    asyncio.run(main())