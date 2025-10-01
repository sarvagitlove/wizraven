from typing import List
from sentence_transformers import SentenceTransformer

class EmbeddingManager:
    def __init__(self, model_name: str = 'all-MiniLM-L6-v2'):
        """
        Initialize the embedding manager with a sentence transformer model.
        
        Args:
            model_name (str): Name of the sentence transformer model to use
        """
        self.model = SentenceTransformer(model_name)
        
    def encode_text(self, text: str) -> List[float]:
        """
        Generate embeddings for a single text string.
        
        Args:
            text (str): Text to encode
            
        Returns:
            List[float]: Text embedding vector
        """
        return self.model.encode(text).tolist()
        
    def encode_batch(self, texts: List[str]) -> List[List[float]]:
        """
        Generate embeddings for a batch of texts.
        
        Args:
            texts (List[str]): List of texts to encode
            
        Returns:
            List[List[float]]: List of embedding vectors
        """
        return self.model.encode(texts).tolist()