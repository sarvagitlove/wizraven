from typing import Dict, List, Optional, Any
from .base_agent import Agent, Message
from ..utils.llm_client import LLMClient
import re
import json

class AnalyzerAgent(Agent):
    def __init__(self):
        super().__init__(name="analyzer_agent",
                         system_message="""You are an expert network log analyzer agent.
            Your role is to analyze network logs, identify patterns, detect anomalies,
            and provide actionable insights and recommendations.""")
        # Do not create LLM client at init; create per-call so requests can
        # provide API key headers.
        self.llm_client = None
        
    async def analyze_logs(self, structured_data: Dict, focus_query: Optional[str] = None, request_context: Optional[Dict] = None) -> Dict:
        """Analyze structured log data for patterns and insights."""
        try:
            # Prepare context for LLM
            context = {
                "format": structured_data.get("format", "unknown"),
                "log_count": len(structured_data.get("timestamp", [])),
                "focus_query": focus_query
            }
            
            # Build analysis prompt
            prompt = f"""Analyze these network logs:
            
            Format: {context['format']}
            Number of entries: {context['log_count']}
            
            Raw logs:
            {structured_data.get('raw_text', '')[:1000]}
            
            {f'Focus on: {focus_query}' if focus_query else 'Provide a comprehensive analysis including:'}
            1. Key patterns and trends
            2. Potential issues or anomalies
            3. Security implications
            4. Performance insights
            5. Actionable recommendations
            """
            
            # First, run a deterministic rule-based analysis so we always have
            # useful output even when an LLM call fails or API key is missing.
            rule_result = self._rule_based_analysis(structured_data, focus_query)

            # Get analysis from LLM (create client per-call)
            # Merge structured-data-derived context with request_context so callers can provide API keys
            merged_context = dict(context)
            if request_context and isinstance(request_context, dict):
                merged_context.update(request_context)
            api_key = merged_context.get('cerebras_api_key') or merged_context.get('api_key')
            client = LLMClient(api_key=api_key)

            # Prefer asking LLM to return structured JSON for easier parsing
            json_prompt = f"""Analyze the following network logs and structured data. Return ONLY a JSON object with keys: summary (string), findings (list of strings), recommendations (list of strings), severity (one of critical/warning/info), patterns (list).\n\nStructured data:\n{structured_data}\n\nIf you cannot produce JSON, return plain text."""

            # If no API key, return the rule-based result immediately
            if not api_key:
                return rule_result

            analysis_raw = None
            try:
                analysis_raw = await client.analyze_text(json_prompt, merged_context)
            except Exception:
                # Fallback to plain prompt
                try:
                    analysis_raw = await client.analyze_text(prompt, merged_context)
                except Exception:
                    # If LLM calls fail, return rule-based result
                    return rule_result

            # Try to parse JSON from LLM
            import json
            try:
                parsed = json.loads(analysis_raw)
                # Merge: prefer LLM fields but fall back to rule_result values
                analysis_results = {
                    "summary": parsed.get('summary', rule_result.get('summary', '')),
                    "severity": parsed.get('severity', rule_result.get('severity', 'info')),
                    "findings": parsed.get('findings', rule_result.get('findings', [])),
                    "recommendations": parsed.get('recommendations', rule_result.get('recommendations', [])),
                    "patterns": parsed.get('patterns', rule_result.get('patterns', []))
                }
            except Exception:
                # If parsing fails, return rule-based result augmented with LLM freeform text
                analysis = analysis_raw or ''
                analysis_results = {
                    "summary": analysis[:200] + ('...' if len(analysis) > 200 else ''),
                    "severity": self._determine_severity(analysis),
                    "findings": rule_result.get('findings', []),
                    "recommendations": rule_result.get('recommendations', []),
                    "patterns": rule_result.get('patterns', [])
                }

            return analysis_results
            
        except Exception as e:
            await self.send_message(f"Error analyzing logs: {str(e)}")
            return {}
        
    async def identify_anomalies(self, data: Dict) -> List[Dict]:
        """Identify anomalies in the log data."""
        try:
            # Use LLM to identify anomalies
            prompt = f"""Review these log patterns and identify potential anomalies:
            
            Log data:
            {str(data)[:1000]}
            
            Focus on:
            1. Unusual patterns
            2. Error frequencies
            3. Timing irregularities
            4. Security concerns
            5. Performance issues
            
            Format findings as:
            - Type: [anomaly type]
            - Severity: [high/medium/low]
            - Description: [details]
            """
            
            api_key = None
            if isinstance(data, dict) and data.get('context'):
                ctx = data.get('context')
                api_key = ctx.get('cerebras_api_key') or ctx.get('api_key')
            client = LLMClient(api_key=api_key)
            analysis = await client.analyze_text(prompt)
            
            # Parse the response into structured anomalies
            # TODO: Implement proper parsing of LLM response
            return [
                {
                    "type": "sample_anomaly",
                    "severity": "medium",
                    "description": analysis
                }
            ]
            
        except Exception as e:
            await self.send_message(f"Error identifying anomalies: {str(e)}")
            return []
            
    async def process_message(self, message: Message) -> None:
        """Process incoming messages and generate responses."""
        try:
            # Extract structured data from context if available
            structured_data = message.context.get("structured_data", {})
            
            # Analyze the data
            analysis = await self.analyze_logs(
                structured_data,
                focus_query=message.content
            )
            
            # Send the response
            await self.send_message(
                analysis["summary"],
                metadata=analysis
            )
            
        except Exception as e:
            await self.send_message(f"Error processing message: {str(e)}")
            
    def _determine_severity(self, analysis: str) -> str:
        """Determine overall severity from analysis."""
        if any(word in analysis.lower() for word in ["critical", "severe", "urgent"]):
            return "critical"
        elif any(word in analysis.lower() for word in ["warning", "attention", "moderate"]):
            return "warning"
        return "info"
        
    def _extract_findings(self, analysis: str) -> List[str]:
        """Extract key findings from analysis text."""
        # TODO: Implement better finding extraction
        return [line.strip() for line in analysis.split("\n") if line.strip()]
        
    def _extract_recommendations(self, analysis: str) -> List[str]:
        """Extract recommendations from analysis text."""
        # TODO: Implement better recommendation extraction
        return [line.strip() for line in analysis.split("\n") if "recommend" in line.lower()]
        
    def _extract_patterns(self, data: Dict) -> List[Dict]:
        """Extract patterns from structured data."""
        # TODO: Implement pattern detection
        return []

    def _rule_based_analysis(self, structured_data: Dict, focus_query: Optional[str] = None) -> Dict:
        """Simple deterministic analysis based on counts and keywords."""
        findings = []
        recommendations = []

        raw = structured_data.get('raw_text', '')
        messages = structured_data.get('message', [])

        # Count errors and link-down events
        error_count = len([m for m in messages if 'error' in m.lower() or 'down' in m.lower() or 'fail' in m.lower()])
        info_count = len(messages) - error_count

        if error_count > 0:
            findings.append(f"Detected {error_count} error-like or down events in the sample.")
            recommendations.append("Investigate the affected interfaces and check recent configuration or hardware changes.")

        if 'cpu' in raw.lower() or 'high cpu' in raw.lower():
            findings.append("Possible CPU-related issue detected.")
            recommendations.append("Check process CPU usage on devices and consider rebooting or updating firmware if recurring.")

        if not findings and messages:
            findings.append("No obvious critical issues found in the sample; logs look informational.")
            recommendations.append("Collect more logs over a longer time window for trend analysis.")

        summary = ' '.join(findings[:2]) if findings else 'No significant issues identified.'
        severity = 'critical' if error_count > 5 else 'warning' if error_count > 0 else 'info'

        return {
            'summary': summary,
            'severity': severity,
            'findings': findings,
            'recommendations': recommendations,
            'patterns': structured_data.get('patterns', [])
        }

    async def analyze_mixed_input(self, text: str, api_key: Optional[str], context: Optional[List[dict]] = None) -> Dict[str, Any]:
        """Analyze mixed input which may contain logs and/or conceptual questions.

        Returns combined JSON with optional log_analysis and qa_response.
        """
        try:
            if not text or not text.strip():
                return {"error": "Empty text"}

            lines = [l.strip() for l in text.splitlines() if l.strip()]

            # Simple heuristics to detect logs vs questions
            log_pattern = re.compile(r"(%[A-Z]+-|Interface|line protocol|LINK-|LINEPROTO|\d{2}:\d{2}:\d{2})", re.I)
            log_hits = sum(1 for l in lines if log_pattern.search(l))
            logs_present = log_hits > 0 or (len(lines) > 2 and any(re.search(r"\d{2}:\d{2}:\d{2}", l) for l in lines[:5]))

            # Expanded question detection to include conversational patterns
            question_pattern = re.compile(r"\b(how|why|what|when|where|explain|recommend|should|could|help|hi|hello|issue|problem|trouble|connectivity|configure|setup|can|does|is|are|will)\b", re.I)
            question_present = bool(question_pattern.search(text) or '?' in text)
            
            # Also treat short conversational inputs as questions
            conversational = len(text.strip()) < 100 and not logs_present
            
            # If it's clearly conversational or a question, treat as question
            if conversational or question_present:
                question_present = True

            result: Dict[str, Any] = {
                "log_analysis": None,
                "qa_response": None,
                "follow_up_needed": False
            }

            # If no clear signal, ask follow-up
            if not logs_present and not question_present:
                result["follow_up_needed"] = True
                result["follow_up_questions"] = [
                    "Do you want me to analyze logs, answer a networking question, or both?",
                    "If this is a log paste, please include a few lines with timestamps or interface names."
                ]
                return result

            # Prepare an LLM client if API key provided
            client = LLMClient(api_key=api_key) if api_key else None
            merged_context = {"history": context or []}
            if api_key:
                merged_context['cerebras_api_key'] = api_key

            # Logs: do deterministic analysis first, then try LLM for root-cause/recommendations
            if logs_present:
                structured = {
                    'raw_text': text,
                    'message': lines,
                    'patterns': []
                }

                rule = self._rule_based_analysis(structured)

                log_analysis = {
                    'root_cause': rule.get('summary', ''),
                    'recommendations': '\n'.join(rule.get('recommendations', [])) if rule.get('recommendations') else '',
                    'severity': rule.get('severity', 'info')
                }

                # If API key and client available, ask Cerebras for structured root-cause
                llm_error = None
                api_key_invalid = False
                if client is not None:
                    prompt = (
                        "You are a network engineer assistant. Given the following network logs, "
                        "return a JSON object with keys: root_cause (string), recommendations (list of strings), severity (High/Medium/Low). "
                        "If uncertain, be conservative and include follow-up questions.\n\n"
                        f"Logs:\n{text}\n"
                    )
                    try:
                        raw = await client.analyze_text(prompt, merged_context)
                        parsed = json.loads(raw)
                        # Normalize
                        log_analysis = {
                            'root_cause': parsed.get('root_cause', log_analysis['root_cause']),
                            'recommendations': '\n'.join(parsed.get('recommendations', [])) if isinstance(parsed.get('recommendations', []), list) else parsed.get('recommendations', ''),
                            'severity': parsed.get('severity', log_analysis['severity']).capitalize()
                        }
                    except Exception as e:
                        # Capture error and keep rule-based analysis
                        llm_error = str(e)
                        if llm_error and ('API key not valid' in llm_error or 'API_KEY_INVALID' in llm_error or 'invalid api key' in llm_error.lower() or 'unauthorized' in llm_error.lower()):
                            api_key_invalid = True

                result['log_analysis'] = log_analysis
                if llm_error:
                    # Attach a small non-sensitive hint indicating LLM failure
                    result.setdefault('hints', {})
                    result['hints']['llm_error_hint'] = 'LLM call failed; check API key and Generative API access.' if api_key_invalid else 'LLM call failed; see server logs for details.'

            # Question / conceptual: query Cerebras for guidance
            if question_present:
                qa_text = None
                # If the logs LLM call already set llm_error/api_key_invalid, reuse that information
                if client is not None:
                    qprompt = (
                        "You are an expert networking engineer. Answer the following question concisely and provide steps if applicable:\n\n" + text
                    )
                    try:
                        qa_text = await client.analyze_text(qprompt, merged_context)
                    except Exception as e:
                        llm_err_text = str(e)
                        # Detect API key issues
                        if ('API key not valid' in llm_err_text or 'API_KEY_INVALID' in llm_err_text or 'invalid api key' in llm_err_text.lower() or 'unauthorized' in llm_err_text.lower()):
                            qa_text = "(LLM error: API key invalid or not authorized) Please check your Cerebras API key and ensure it's valid."
                        else:
                            qa_text = "(Cerebras unavailable or API call failed) Please try again or provide a valid API key."
                        # attach hint
                        result.setdefault('hints', {})
                        result['hints']['llm_error_hint'] = 'API key invalid or not authorized' if ('API key not valid' in llm_err_text or 'API_KEY_INVALID' in llm_err_text or 'invalid api key' in llm_err_text.lower() or 'unauthorized' in llm_err_text.lower()) else 'LLM call failed'
                else:
                    # Provide helpful fallback responses for common networking questions
                    qa_text = self._generate_fallback_response(text)

                result['qa_response'] = qa_text

            # If either LLM indicated uncertainty, set follow-up
            if (result.get('qa_response') and isinstance(result.get('qa_response'), str) and 'uncertain' in result.get('qa_response').lower()):
                result['follow_up_needed'] = True

            return result

        except Exception as e:
            # Graceful failure
            return {"error": str(e)}

    def _generate_fallback_response(self, text: str) -> str:
        """Generate helpful fallback responses for common networking questions when no API key is available."""
        text_lower = text.lower()
        
        # Greetings and casual conversation
        if any(greeting in text_lower for greeting in ['hi', 'hello', 'hey', 'good morning', 'good afternoon']):
            return "Hello! I'm your networking assistant. I can help you with BGP, OSPF, switching, routing, troubleshooting, and network configuration questions. For enhanced AI responses, you can provide a Cerebras API key. How can I assist you today?"
        
        # BGP related questions
        if 'bgp' in text_lower:
            if any(word in text_lower for word in ['connectivity', 'connection', 'issue', 'problem', 'trouble', 'down', 'not working']):
                return """BGP connectivity issues are common. Here are the key troubleshooting steps:

1. **Check BGP neighbor status**: `show ip bgp summary`
2. **Verify connectivity**: Ping the neighbor IP
3. **Check routing**: Ensure routes to neighbor exist
4. **Verify configuration**: AS numbers, router IDs, authentication
5. **Check BGP state**: Look for Idle, Connect, Active states
6. **Review logs**: Look for BGP error messages

Common causes:
- Incorrect AS numbers
- Authentication mismatch  
- Network connectivity issues
- Firewall blocking TCP 179
- BGP timers mismatch

Would you like me to help with specific BGP configuration or provide more detailed troubleshooting steps?"""
            else:
                return """BGP (Border Gateway Protocol) is the routing protocol of the internet. I can help with:

- BGP configuration and setup
- Neighbor relationships and peering
- Route advertisement and filtering
- Troubleshooting connectivity issues
- Best path selection
- Route maps and policies

What specific BGP topic would you like help with?"""
        
        # OSPF related
        if 'ospf' in text_lower:
            return """OSPF (Open Shortest Path First) is a link-state routing protocol. Common topics include:

- OSPF areas and hierarchy
- LSA types and database synchronization  
- Neighbor adjacencies and DR/BDR election
- Authentication and security
- Troubleshooting convergence issues

What OSPF topic can I help you with?"""
        
        # General networking issues
        if any(word in text_lower for word in ['issue', 'problem', 'trouble', 'help', 'broken', 'not working']):
            return """I'm here to help with networking issues! To provide better assistance, please tell me:

1. What type of network problem are you experiencing?
2. What devices/protocols are involved? (routers, switches, BGP, OSPF, etc.)
3. Any error messages or symptoms you're seeing?

Common networking issues I can help with:
- Routing protocol problems (BGP, OSPF, EIGRP)
- Switching issues (VLANs, STP, trunking)
- Connectivity troubleshooting
- Configuration questions
- Performance optimization

For enhanced troubleshooting with AI analysis, you can provide a Cerebras API key."""
        
        # Configuration questions
        if any(word in text_lower for word in ['configure', 'configuration', 'setup', 'config']):
            return """I can help with network device configuration! Common configuration topics include:

- Router configuration (BGP, OSPF, static routes)
- Switch configuration (VLANs, trunking, port security)
- Interface configuration and IP addressing
- Access control lists (ACLs)
- Quality of Service (QoS)
- Security features

What specific configuration do you need help with?"""
        
        # Generic networking question
        if any(word in text_lower for word in ['network', 'networking', 'router', 'switch', 'protocol']):
            return """I'm a networking expert assistant! I can help with:

**Routing Protocols**: BGP, OSPF, EIGRP, RIP
**Switching**: VLANs, STP, trunking, port security  
**Troubleshooting**: Connectivity, performance, configuration issues
**Configuration**: Router/switch setup, security, QoS
**Network Design**: Architecture, best practices, optimization

What networking topic would you like to explore?"""
        
        # Default response for unclear questions
        return """I'm your networking assistant! I can help with network troubleshooting, configuration, and technical questions about:

• Routing protocols (BGP, OSPF, EIGRP)
• Switching technologies (VLANs, STP, trunking)
• Network troubleshooting and diagnostics
• Device configuration and best practices
• Performance optimization

Please feel free to ask any networking question or describe the issue you're facing. For enhanced AI-powered responses, you can optionally provide a Cerebras API key."""