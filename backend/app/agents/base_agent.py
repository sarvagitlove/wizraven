from typing import Any, Dict, Optional

class Message:
    def __init__(self, content: str, context: Optional[Dict[str, Any]] = None):
        self.content = content
        self.context = context or {}

class Agent:
    def __init__(self, name: str, system_message: str = ""):
        self.name = name
        self.system_message = system_message

    async def send_message(self, content: str, metadata: Optional[Dict[str, Any]] = None):
        """Placeholder send_message - agents should override or the app will collect responses."""
        # In this simplified replacement we just print; the main app can wire this to real transports.
        print(f"[{self.name}] {content}")

    async def process_message(self, message: Message) -> None:
        raise NotImplementedError()
