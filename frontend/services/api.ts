import axios from 'axios';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8001';

export interface Message {
  content: string;
  context?: Record<string, any>;
}

export interface AgentResponse {
  agent_type: string;
  content: string;
  metadata?: Record<string, any>;
}

export const analyzeInteractive = async (message: Message): Promise<AgentResponse[]> => {
  try {
    const headers: Record<string, string> = {};
    try {
      const key = localStorage.getItem('cerebras_api_key');
      if (key) headers['X-Cerebras-Api-Key'] = key;
    } catch (e) {
      // ignore
    }
    const response = await axios.post(`${API_URL}/api/analyze/interactive`, message, { headers });
    return response.data;
  } catch (error) {
    console.error('Error in analyzeInteractive:', error);
    throw error;
  }
};

export const analyzeLogs = async (message: Message): Promise<AgentResponse[]> => {
  try {
    const headers: Record<string, string> = {};
    try {
      const key = localStorage.getItem('cerebras_api_key');
      if (key) headers['X-Cerebras-Api-Key'] = key;
    } catch (e) {}
    const response = await axios.post(`${API_URL}/api/analyze`, message, { headers });
    return response.data;
  } catch (error) {
    console.error('Error in analyzeLogs:', error);
    throw error;
  }
};

export const queryKnowledgeBase = async (message: Message): Promise<AgentResponse[]> => {
  try {
    const headers: Record<string, string> = {};
    try {
      const key = localStorage.getItem('cerebras_api_key');
      if (key) headers['X-Cerebras-Api-Key'] = key;
    } catch (e) {}
    const response = await axios.post(`${API_URL}/api/query`, message, { headers });
    return response.data;
  } catch (error) {
    console.error('Error in queryKnowledgeBase:', error);
    throw error;
  }
};