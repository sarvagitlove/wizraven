import os
from typing import Dict, List
import google.generativeai as genai
from dotenv import load_dotenv

load_dotenv()

class LLMClient:
    def __init__(self):
        """
        Initialize the LLM client with API credentials.
        """
        api_key = os.getenv('GEMINI_API_KEY')
        if not api_key:
            raise ValueError("GEMINI_API_KEY not found in environment variables")
            
        genai.configure(api_key=api_key)
        self.model = genai.GenerativeModel('gemini-pro')
        
    async def analyze_text(self, text: str, context: Dict = None) -> str:
        """
        Analyze text using the Gemini model.
        
        Args:
            text (str): Text to analyze
            context (Dict, optional): Additional context for analysis
            
        Returns:
            str: Model's analysis response
        """
        prompt = self._build_prompt(text, context)
        response = self.model.generate_content(prompt)
        return response.text
        
    def _build_prompt(self, text: str, context: Dict = None) -> str:
        """
        Build a prompt for the model.
        
        Args:
            text (str): Main text to analyze
            context (Dict, optional): Additional context
            
        Returns:
            str: Formatted prompt
        """
        # TODO: Implement more sophisticated prompt engineering
        return f"""Analyze the following network log data:
        
{text}

Provide a detailed analysis including:
1. Key issues identified
2. Potential root causes
3. Recommended actions"""