# Wizraven 🔍

Wizraven is an AI-powered log analyzer designed specifically for network engineers. It leverages advanced language models and vector search to provide intelligent analysis of network logs, making troubleshooting faster and more efficient.

## 🚀 Features (MVP)

- **Intelligent Log Parsing**: Automatically identifies and parses various network log formats
- **Context-Aware Analysis**: Utilizes AG2 agents to analyze logs with deep networking knowledge
- **Knowledge Base Integration**: Built-in access to networking RFCs and vendor documentation
- **Interactive UI**: Clean, modern interface for log submission and analysis results
- **API-First Design**: RESTful API endpoints for easy integration

## 🛠 Tech Stack

### Frontend
- Next.js (hosted on Vercel)
- TypeScript
- Modern UI components
- Responsive design

### Backend
- FastAPI (hosted on Render)
- AG2 Agents for modular analysis
- FAISS for vector search
- Cerebras AI for LLM capabilities
- Python 3.9+

## 🏗 Architecture

The system is built with a modular architecture consisting of:

- **Parser Agent**: Identifies and extracts structured data from raw logs
- **Analyzer Agent**: Performs deep analysis of parsed logs
- **Knowledge Agent**: Manages and queries the FAISS knowledge base
- **Crawler Agent**: Keeps knowledge base updated with latest docs

## 🚦 Getting Started

### Prerequisites
- Node.js 18+
- Python 3.9+
- Docker (optional)

### Frontend Setup
```bash
cd frontend
npm install
npm run dev
```

### Backend Setup
```bash
cd backend
python -m venv venv
source venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --reload
```

### Environment Variables
Copy `.env.example` to `.env` and fill in your API keys:
```
CEREBRAS_API_KEY=your_key_here
```

## 📝 Roadmap

- [x] MVP with Cerebras AI integration
- [ ] Support for multiple log formats
- [ ] Custom LLM integration options
- [ ] Enhanced visualization features
- [ ] Team collaboration features
- [ ] Self-hosted deployment options

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](docs/CONTRIBUTING.md) for details.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Cerebras AI team
- FastAPI community
- Next.js team
- All our contributors