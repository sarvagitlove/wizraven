import React, { useEffect, useRef, useState, useLayoutEffect } from 'react'
import {
  Box,
  Button,
  Flex,
  Input,
  Text,
  VStack,
  HStack,
  chakra,
  Tag,
  TagLabel,
  Avatar,
  Spacer,
  Stack,
  InputGroup,
  InputRightElement,
  Badge,
  useToast,
  FormControl,
  FormLabel,
  Textarea,
  Icon,
} from '@chakra-ui/react'
import { MdMemory, MdChat } from 'react-icons/md'

import { analyzeInteractive, analyzeLogs } from '../services/api'

interface Message {
  id: string
  content: string
  role: 'user' | 'agent'
  agentType?: 'parser' | 'analyzer' | 'knowledge' | 'crawler'
  timestamp: Date
  status: 'pending' | 'complete' | 'error'
  metadata?: Record<string, any>
}

interface ConversationContext {
  conversationId: string
  parsedData?: Record<string, any>
  lastAnalysis?: Record<string, any>
}

export default function LogInput() {
  const [input, setInput] = useState('')
  const [conversation, setConversation] = useState<Message[]>([])
  const [isProcessing, setIsProcessing] = useState(false)
  const [context, setContext] = useState<ConversationContext>({ conversationId: '' })
  const [apiKey, setApiKey] = useState<string>('')
  const [isClient, setIsClient] = useState(false)
  const formRef = useRef<HTMLDivElement | null>(null)
  const [scrollPaddingPx, setScrollPaddingPx] = useState<number>(160)

  const toast = useToast()
  const scrollRef = useRef<HTMLDivElement | null>(null)
  const inputRef = useRef<HTMLTextAreaElement | null>(null)

  const saveApiKey = (key?: string) => {
    if (!isClient) return // Only run on client side
    
    const v = key ?? apiKey
    try {
      if (v) localStorage.setItem('cerebras_api_key', v)
      else localStorage.removeItem('cerebras_api_key')
      setApiKey(v)
      toast({ title: 'API Key saved', status: 'success', duration: 2000 })
    } catch (e) {
      toast({ title: 'Unable to save key', status: 'error' })
    }
  }

  useEffect(() => {
    // Set client-side flag and initialize client-only state
    setIsClient(true)
    setContext({ conversationId: Date.now().toString() })
    
    // Load API key from localStorage on client side only
    try {
      const savedKey = localStorage.getItem('cerebras_api_key') || ''
      setApiKey(savedKey)
    } catch (e) {
      // localStorage not available
    }
    
    // autofocus textarea on mount
    if (inputRef.current) inputRef.current.focus()
  }, [])

  useEffect(() => {
    if (!scrollRef.current) return
    // small delay so DOM updates (new messages) are rendered before scrolling
    const id = window.setTimeout(() => {
      try {
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' })
      } catch (e) {
        // fallback
        if (scrollRef.current) scrollRef.current.scrollTop = scrollRef.current.scrollHeight
      }
    }, 60)
    return () => window.clearTimeout(id)
  }, [conversation])

  // Measure the form height (input area) and set the bottom padding for the scrollable messages.
  // Use a responsive buffer: smaller on mobile, larger on desktop.
  const measureFormPadding = () => {
    try {
      const h = formRef.current ? formRef.current.offsetHeight : 0
      const isNarrow = typeof window !== 'undefined' ? window.innerWidth < 600 : false
      const buffer = isNarrow ? 64 : 16
      const minPad = isNarrow ? 80 : 120
      const padded = Math.max(minPad, h + buffer)
      setScrollPaddingPx(padded)
    } catch (e) {
      // ignore measurement errors
    }
  }

  useEffect(() => {
    // initial measurement
    measureFormPadding()

    // prefer ResizeObserver for robust measurement of the form element
    let ro: ResizeObserver | null = null
    if (typeof window !== 'undefined' && typeof (window as any).ResizeObserver !== 'undefined' && formRef.current) {
      ro = new (window as any).ResizeObserver(() => {
        measureFormPadding()
      })
      ro.observe(formRef.current)
    } else {
      // fallback
      const onResize = () => measureFormPadding()
      window.addEventListener('resize', onResize)
      return () => window.removeEventListener('resize', onResize)
    }

    return () => {
      if (ro) ro.disconnect()
    }
  }, [input])

    const handleSubmit = async (e?: React.FormEvent) => {
    if (e && typeof e.preventDefault === 'function') e.preventDefault()

    if (!input.trim()) {
      toast({ title: 'Error', description: 'Please enter a message or log content', status: 'error', duration: 3000, isClosable: true })
      return
    }

    try {
      setIsProcessing(true)
      // Generate message ID using current timestamp
      const messageId = isClient ? Date.now().toString() : Math.random().toString(36)
      const newMessage: Message = {
        id: messageId,
        content: input,
        role: 'user',
        timestamp: new Date(),
        status: 'pending',
      }

      setConversation((prev) => [...prev, newMessage])
      setInput('')

      // ensure scroll moves to bottom after user message
      setTimeout(() => {
        if (scrollRef.current) scrollRef.current.scrollTop = scrollRef.current.scrollHeight
      }, 50)

      // Build context that includes api key so the backend can pick it up (optional)
      const ctx = { ...context } as any
      if (apiKey) ctx.cerebras_api_key = apiKey      // Call the Analyzer-only API endpoint
      // analyzeLogs returns an array of AgentResponse-like objects in older flows
      // but Analyzer-only /api/analyze returns a single structured object. Handle both.
      let responses: any[] = []
      const result = await analyzeLogs({ content: newMessage.content, context: { ...ctx, messageId: newMessage.id } })
      // If the backend returned an array (legacy), use it directly; otherwise wrap the single analyzer response
      if (Array.isArray(result)) responses = result
      else if (result && typeof result === 'object') {
        // Expecting analyzer-only shape: { log_analysis, qa_response, follow_up_needed, follow_up_questions? }
        // Convert into a display-friendly agent response object for the UI
        const analyzerBlob = result as any
        const displayContentParts: string[] = []
        if (analyzerBlob.log_analysis) {
          const la = analyzerBlob.log_analysis
          if (la.root_cause) displayContentParts.push(`Root cause: ${la.root_cause}`)
          if (la.recommendations) {
            if (Array.isArray(la.recommendations)) displayContentParts.push(`Recommendations:\n- ${la.recommendations.join('\n- ')}`)
            else displayContentParts.push(`Recommendations: ${la.recommendations}`)
          }
        }
        if (analyzerBlob.qa_response) displayContentParts.push(`Answer: ${analyzerBlob.qa_response}`)

        const synthesized: any[] = [
          {
            agent_type: 'analyzer',
            content: displayContentParts.join('\n\n'),
            metadata: {
              log_analysis: analyzerBlob.log_analysis,
              qa_response: analyzerBlob.qa_response,
              follow_up_needed: analyzerBlob.follow_up_needed,
              follow_up_questions: analyzerBlob.follow_up_questions || [],
            },
          },
        ]

        // Also, if the analyzer provided follow-up questions, add them as a separate agent message
        responses = synthesized
        if (analyzerBlob.follow_up_needed && Array.isArray(analyzerBlob.follow_up_questions) && analyzerBlob.follow_up_questions.length > 0) {
          responses.push({ agent_type: 'analyzer', content: `Follow-up questions:\n- ${analyzerBlob.follow_up_questions.join('\n- ')}`, metadata: { follow_ups: analyzerBlob.follow_up_questions } })
        }
      }
      // Debug: print raw responses so devtools shows what we received
      // This helps verify the frontend got the same structured metadata the backend returned
      // (open browser console to inspect)
      // eslint-disable-next-line no-console
      console.log('API responses', responses)

      // Update context with any new data
      const newContext = { ...context } as any
      for (const response of responses) {
        if (response.agent_type === 'parser' && response.metadata) newContext.parsedData = response.metadata
        if (response.agent_type === 'analyzer' && response.metadata) newContext.lastAnalysis = response.metadata
      }
      setContext(newContext)

      // Add agent responses to the conversation
      const agentMessages: Message[] = responses.map((response, index) => ({
        id: isClient ? (Date.now().toString() + '-' + response.agent_type) : (Math.random().toString(36) + '-' + response.agent_type),
        content: response.content,
        role: 'agent',
        agentType: response.agent_type as any,
        timestamp: new Date(),
        status: 'complete',
        metadata: response.metadata,
      }))

      setConversation((prev) => [...prev, ...agentMessages])
    } catch (error) {
      toast({ title: 'Error', description: 'Failed to process the request', status: 'error', duration: 5000, isClosable: true })
    } finally {
      setIsProcessing(false)
    }
  }

  const handleKeyDown: React.KeyboardEventHandler<HTMLTextAreaElement> = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault()
      if (input.trim() && !isProcessing) {
        void handleSubmit()
      }
    }
  }

  return (
    <VStack spacing={6} h="full">
      {/* API Key Configuration Header */}
      <Box w="full" p={6} bg="linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%)" borderRadius="xl" border="1px solid" borderColor="gray.200">
        <HStack justify="space-between" align="center">
          <HStack spacing={4}>
            <Box p={2} bg="blue.500" borderRadius="lg">
              <Icon as={MdMemory} color="white" />
            </Box>
            <VStack align="start" spacing={0}>
              <Text fontSize="lg" fontWeight="bold" color="gray.800">Cerebras AI Configuration</Text>
              <Text fontSize="sm" color="gray.600">Optional: Enhanced AI responses with your API key</Text>
            </VStack>
          </HStack>
          <HStack spacing={3}>
            <InputGroup size="md" maxW="300px">
              <Input 
                placeholder="API key for enhanced responses (optional)" 
                value={apiKey} 
                onChange={(e) => setApiKey(e.target.value)} 
                type="password" 
                bg="white" 
                borderColor="gray.300"
                _focus={{ borderColor: "blue.500", boxShadow: "0 0 0 1px #3182ce" }}
              />
              <InputRightElement width="4.5rem">
                <Button size="sm" colorScheme="blue" onClick={() => saveApiKey()}>
                  Save
                </Button>
              </InputRightElement>
            </InputGroup>
            <Button size="sm" variant="ghost" colorScheme="gray" onClick={() => { setApiKey(''); saveApiKey(''); }}>
              Clear
            </Button>
          </HStack>
        </HStack>
      </Box>

      {/* Chat Interface */}
      <Box 
        w="full" 
        flex="1" 
        bg="white" 
        borderRadius="xl" 
        border="1px solid" 
        borderColor="gray.200" 
        boxShadow="lg"
        overflow="hidden"
        display="flex"
        flexDirection="column"
      >
        {/* Chat Header */}
        <Box p={4} bg="linear-gradient(135deg, #667eea 0%, #764ba2 100%)" color="white">
          <HStack spacing={3}>
            <Box p={2} bg="whiteAlpha.200" borderRadius="lg">
              <Icon as={MdChat} fontSize="xl" color="white" />
            </Box>
            <VStack align="start" spacing={0}>
              <Text fontWeight="bold">Conversation</Text>
              <Text fontSize="sm" opacity={0.9}>Free AI-powered analysis (API key optional)</Text>
            </VStack>
            <Spacer />
            {apiKey ? (
              <Badge colorScheme="green" borderRadius="full" px={3} py={1}>
                Enhanced Mode
              </Badge>
            ) : (
              <Badge colorScheme="blue" borderRadius="full" px={3} py={1}>
                Demo Mode
              </Badge>
            )}
          </HStack>
        </Box>

        {/* Messages Area */}
        <Box 
          flex="1" 
          overflowY="auto" 
          ref={scrollRef} 
          p={6} 
          pb={`${scrollPaddingPx}px`}
          sx={{ 
            '&::-webkit-scrollbar': { width: '6px' }, 
            '&::-webkit-scrollbar-thumb': { bg: 'gray.300', borderRadius: '3px' },
            scrollbarWidth: 'thin'
          }}
        >
          <VStack spacing={6} align="stretch">
            {conversation.length === 0 && (
              <VStack spacing={4} py={12} textAlign="center">
                <Box p={4} bg="blue.50" borderRadius="full" display="inline-flex">
                  <Icon as={MdChat} fontSize="2xl" color="blue.500" />
                </Box>
                <VStack spacing={2}>
                  <Text fontSize="lg" fontWeight="semibold" color="gray.700">
                    Ready to chat and analyze!
                  </Text>
                  <Text color="gray.500" maxW="md">
                    Ask me anything about networking, paste logs for analysis, or have a general conversation. No API key required for basic interactions!
                  </Text>
                </VStack>
              </VStack>
            )}

            {conversation.map((message) => {
              const isAgent = message.role === 'agent'
              const displayContent =
                (message.metadata && (message.metadata.summary || (Array.isArray(message.metadata.findings) && message.metadata.findings.join('\n')))) ||
                message.content ||
                ''

              return (
                <HStack key={message.id} align="start" justify={isAgent ? 'flex-start' : 'flex-end'} spacing={3}>
                  {isAgent && (
                    <Box p={2} bg="blue.500" borderRadius="full" color="white" minW="32px" h="32px" display="flex" alignItems="center" justifyContent="center">
                      <Text fontSize="sm" fontWeight="bold">AI</Text>
                    </Box>
                  )}
                  
                  <Box
                    bg={isAgent ? 'gray.50' : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'}
                    color={isAgent ? 'gray.800' : 'white'}
                    px={5}
                    py={4}
                    borderRadius="2xl"
                    maxW="70%"
                    boxShadow="md"
                    border={isAgent ? "1px solid" : "none"}
                    borderColor={isAgent ? "gray.200" : "transparent"}
                  >
                    <Text whiteSpace="pre-wrap" lineHeight="1.6">{displayContent}</Text>

                    {/* Enhanced metadata display */}
                    {message.metadata && (
                      <VStack mt={4} spacing={3} align="start">
                        {message.metadata.summary && (
                          <Box w="full">
                            <Text fontWeight="bold" fontSize="sm" mb={2} color={isAgent ? "blue.600" : "blue.100"}>
                              📊 Summary
                            </Text>
                            <Text fontSize="sm" opacity={isAgent ? 0.8 : 0.9}>{message.metadata.summary}</Text>
                          </Box>
                        )}

                        {Array.isArray(message.metadata.findings) && message.metadata.findings.length > 0 && (
                          <Box w="full">
                            <Text fontWeight="bold" fontSize="sm" mb={2} color={isAgent ? "orange.600" : "orange.100"}>
                              🔍 Findings
                            </Text>
                            <VStack align="start" spacing={2}>
                              {message.metadata.findings.map((f: string, i: number) => (
                                <HStack key={i} align="start" spacing={2}>
                                  <Box w={2} h={2} bg={isAgent ? "orange.400" : "orange.200"} borderRadius="full" mt={2} flexShrink={0} />
                                  <Text fontSize="sm" opacity={isAgent ? 0.8 : 0.9}>{f}</Text>
                                </HStack>
                              ))}
                            </VStack>
                          </Box>
                        )}

                        {Array.isArray(message.metadata.recommendations) && message.metadata.recommendations.length > 0 && (
                          <Box w="full">
                            <Text fontWeight="bold" fontSize="sm" mb={2} color={isAgent ? "green.600" : "green.100"}>
                              💡 Recommendations
                            </Text>
                            <VStack align="start" spacing={2}>
                              {message.metadata.recommendations.map((r: string, i: number) => (
                                <HStack key={i} align="start" spacing={2}>
                                  <Box w={2} h={2} bg={isAgent ? "green.400" : "green.200"} borderRadius="full" mt={2} flexShrink={0} />
                                  <Text fontSize="sm" opacity={isAgent ? 0.8 : 0.9}>{r}</Text>
                                </HStack>
                              ))}
                            </VStack>
                          </Box>
                        )}

                        {Array.isArray(message.metadata.patterns) && message.metadata.patterns.length > 0 && (
                          <HStack mt={3} spacing={2} flexWrap="wrap">
                            {message.metadata.patterns.map((p: string, i: number) => (
                              <Tag 
                                key={i} 
                                size="sm" 
                                bg={isAgent ? "purple.100" : "whiteAlpha.200"} 
                                color={isAgent ? "purple.700" : "white"}
                                borderRadius="full"
                              >
                                <TagLabel>{p}</TagLabel>
                              </Tag>
                            ))}
                          </HStack>
                        )}
                      </VStack>
                    )}
                  </Box>

                  {!isAgent && (
                    <Box p={2} bg="gray.600" borderRadius="full" color="white" minW="32px" h="32px" display="flex" alignItems="center" justifyContent="center">
                      <Text fontSize="sm" fontWeight="bold">You</Text>
                    </Box>
                  )}
                </HStack>
              )
            })}
          </VStack>
        </Box>

        {/* Input Area */}
        <Box p={6} bg="gray.50" borderTop="1px solid" borderColor="gray.200" ref={formRef}>
          <Box as="form" onSubmit={(e) => { e.preventDefault(); void handleSubmit(); }}>
            <VStack spacing={4}>
              <Box w="full" position="relative">
                <Textarea 
                  ref={inputRef}
                  value={input} 
                  onChange={(e) => setInput(e.target.value)} 
                  onKeyDown={handleKeyDown}
                  placeholder="Ask me anything about networking, paste logs, or have a general conversation... (Ctrl/Cmd+Enter to send)" 
                  minHeight="100px"
                  resize="vertical" 
                  bg="white"
                  border="2px solid"
                  borderColor="gray.200"
                  borderRadius="xl"
                  _focus={{ borderColor: "blue.500", boxShadow: "0 0 0 1px #3182ce" }}
                  _placeholder={{ color: "gray.400" }}
                />
                <Box position="absolute" bottom={3} right={3} pointerEvents="none">
                  <Text fontSize="xs" color="gray.400">Ctrl+Enter to send</Text>
                </Box>
              </Box>

              <HStack width="full" justify="space-between">
                <HStack spacing={2}>
                  <Text fontSize="sm" color="gray.500">
                    {input.length} characters
                  </Text>
                  {isProcessing && (
                    <HStack spacing={2}>
                      <Box w={2} h={2} bg="blue.400" borderRadius="full" />
                      <Text fontSize="sm" color="blue.600">Processing...</Text>
                    </HStack>
                  )}
                </HStack>
                
                <HStack spacing={3}>
                  <Button 
                    variant="ghost" 
                    size="md"
                    onClick={() => { setInput(''); if (inputRef.current) inputRef.current.focus(); }}
                    isDisabled={!input.trim()}
                  >
                    Clear
                  </Button>
                  <Button 
                    type="submit" 
                    colorScheme="blue"
                    size="md"
                    px={8}
                    isDisabled={!input.trim() || isProcessing} 
                    isLoading={isProcessing}
                    loadingText="Analyzing..."
                  >
                    Send
                  </Button>
                </HStack>
              </HStack>
            </VStack>
          </Box>
        </Box>
      </Box>
    </VStack>
  )
}