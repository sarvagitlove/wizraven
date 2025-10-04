#!/usr/bin/env python3
"""
Demo script to test Cerebras AI integration.

This script demonstrates how to use the updated LLMClient with Cerebras AI.
Before running, make sure you have:
1. Set the CEREBRAS_API_KEY environment variable
2. Installed the cerebras-cloud-sdk package
"""

import asyncio
import os
from app.utils.llm_client import LLMClient


async def test_cerebras_integration():
    """Test the Cerebras AI integration."""
    
    # Check if API key is available
    api_key = os.getenv('CEREBRAS_API_KEY')
    if not api_key:
        print("❌ CEREBRAS_API_KEY not found in environment variables.")
        print("Please set your Cerebras API key: export CEREBRAS_API_KEY=your_key_here")
        return
    
    print("🧠 Testing Cerebras AI integration...")
    print(f"✅ Found API key: {api_key[:10]}...")
    
    # Initialize the LLM client
    llm_client = LLMClient()
    
    # Test data - sample network log
    sample_log = """
    2024-01-15 10:30:45 INFO: Connection established from 192.168.1.100
    2024-01-15 10:30:46 WARN: Unusual traffic pattern detected
    2024-01-15 10:30:47 ERROR: Failed authentication attempt from 192.168.1.100
    2024-01-15 10:30:48 INFO: Connection terminated
    """
    
    try:
        print("\n📊 Analyzing sample network log data...")
        result = await llm_client.analyze_text(sample_log)
        print("\n🎯 Cerebras AI Analysis Result:")
        print("-" * 50)
        print(result)
        print("-" * 50)
        print("\n✅ Cerebras AI integration working successfully!")
        
    except ImportError as e:
        print(f"❌ Import error: {e}")
        print("Please install the Cerebras SDK: pip install cerebras-cloud-sdk")
    except Exception as e:
        print(f"❌ Error during analysis: {e}")
        print("Please check your API key and network connection.")


if __name__ == "__main__":
    asyncio.run(test_cerebras_integration())