# Contributing to Wizraven

First off, thank you for considering contributing to Wizraven! It's people like you that make Wizraven such a great tool.

## Code of Conduct

By participating in this project, you are expected to uphold our Code of Conduct:

- Be respectful and inclusive
- Focus on constructive feedback
- Maintain professional discourse
- Support a collaborative environment

## How Can I Contribute?

### Reporting Bugs

1. **Check Existing Issues** - Search the issue tracker to avoid duplicates
2. **Create a Clear Report** - Include:
   - Steps to reproduce
   - Expected behavior
   - Actual behavior
   - Log output
   - Environment details

### Suggesting Enhancements

1. **Describe the Enhancement** - Explain how it would work
2. **Provide Context** - Why is this enhancement valuable?
3. **Consider Scope** - How would it impact existing features?

### Pull Requests

1. **Fork the Repository**
2. **Create a Branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. **Make Your Changes**
   - Follow the coding style
   - Add tests if applicable
   - Update documentation
4. **Commit Your Changes**
   ```bash
   git commit -m "feat: add some feature"
   ```
5. **Push to Your Fork**
   ```bash
   git push origin feature/your-feature-name
   ```
6. **Submit a Pull Request**

## Development Setup

### Frontend (Next.js)
```bash
cd frontend
npm install
npm run dev
```

### Backend (FastAPI)
```bash
cd backend
python -m venv venv
source venv/bin/activate  # or `venv\Scripts\activate` on Windows
pip install -r requirements.txt
uvicorn app.main:app --reload
```

## Coding Guidelines

### Python
- Follow PEP 8
- Use type hints
- Write docstrings
- Include tests

### TypeScript/JavaScript
- Use ESLint configuration
- Follow Prettier formatting
- Write JSDoc comments
- Include unit tests

## Testing

### Running Tests
```bash
# Backend
cd backend
pytest

# Frontend
cd frontend
npm test
```

## Documentation

- Update relevant documentation
- Include docstrings
- Add comments for complex logic
- Update README if needed

## Review Process

1. Automated checks must pass
2. Code review required
3. Documentation review if applicable
4. Testing verification
5. Final approval

## Get in Touch

- GitHub Issues
- Discord Community (coming soon)
- Project Email (coming soon)

Thank you for contributing to Wizraven! 🚀