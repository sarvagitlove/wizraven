from typing import Dict, Optional, Any
from .base_agent import Agent, Message
import re


class ParserAgent(Agent):
    def __init__(self):
        super().__init__(name="parser_agent",
                         system_message="""You are an expert log parser agent specialized in network logs.
            Your role is to preprocess and clean raw log text into a consistent form.""")

        # Predefined simple patterns to help with lightweight format detection
        self.log_patterns = {
            'cisco': r'(\w{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2}).*?%([A-Z0-9\-]+)-',
            'juniper': r'(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})',
            'syslog': r'<\d+>',
        }

    async def process_logs(self, raw_logs: str, context: Optional[Dict[str, Any]] = None) -> Dict:
        """Clean and normalize raw log text.

        Returns a dict with:
          - clean_logs: the cleaned log text
          - original_length: length of the raw input (chars)

        This function intentionally does not call any LLM.
        """
        try:
            original_length = len(raw_logs or "")

            # Normalize newlines and strip leading/trailing whitespace
            text = (raw_logs or '').replace('\r\n', '\n').strip()

            lines = []
            for raw_line in text.split('\n'):
                line = raw_line.strip()
                if not line:
                    continue

                # strip leading asterisks or bullets common in pasted logs
                line = re.sub(r'^\*+\s*', '', line)

                # remove syslog numeric priority like <13>
                line = re.sub(r'^<\d+>\s*', '', line)

                # remove ISO or syslog timestamps at start of line
                line = re.sub(r'^\*?\s*\w{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2}(?:\.\d+)?[:\s-]*', '', line)
                line = re.sub(r'^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}[\s-]*', '', line)

                # If the line contains device-priority markers like %LINK-3-UPDOWN:, keep text after the first ':'
                if ':' in line:
                    parts = line.split(':', 1)
                    # If the left part looks like a header (contains % or uppercase-with-dash), drop it
                    if re.search(r'%[A-Z0-9\-]+', parts[0]) or re.match(r'^[A-Z0-9_\-]{2,}$', parts[0].strip()):
                        line = parts[1].strip()

                # collapse multiple spaces
                line = re.sub(r'\s+', ' ', line).strip()

                if line:
                    lines.append(line)

            clean_logs = '\n'.join(lines)

            return {
                "clean_logs": clean_logs,
                "original_length": original_length,
            }

        except Exception as e:
            # On failure, return a minimal structure
            return {"clean_logs": '', "original_length": len(raw_logs or '')}

    async def identify_log_format(self, sample_logs: str, context: Optional[Dict[str, Any]] = None) -> str:
        """Identify the format/type of the provided logs using simple regex checks.

        This function intentionally avoids calling any external LLM.
        """
        try:
            for format_name, pattern in self.log_patterns.items():
                if re.search(pattern, sample_logs or ''):
                    return format_name
            return 'unknown'
        except Exception:
            return 'unknown'

    async def process_message(self, message: Message) -> None:
        """Process incoming messages by cleaning logs and returning the result as metadata."""
        try:
            result = await self.process_logs(message.content, message.context)
            summary = f"Parsed logs ({result.get('original_length', 0)} chars); cleaned {len(result.get('clean_logs',''))} chars."
            await self.send_message(summary, metadata=result)
        except Exception as e:
            await self.send_message(f"Error processing message: {str(e)}")