import { Box, Container, Heading, Grid, GridItem, Text, VStack, HStack, Icon, Badge } from '@chakra-ui/react';
import { MdMemory, MdFlashOn, MdSecurity } from 'react-icons/md';
import LogInput from '../components/LogInput';

export default function Home() {
  return (
    <Box minH="100vh" bg="linear-gradient(135deg, #667eea 0%, #764ba2 100%)">
      <Container maxW="container.xl" py={12}>
        {/* Header */}
        <VStack spacing={6} textAlign="center" mb={12}>
          <HStack spacing={3} justify="center">
            <Box p={3} bg="whiteAlpha.200" borderRadius="xl" backdropFilter="blur(10px)">
              <Icon as={MdMemory} boxSize={8} color="white" />
            </Box>
            <Heading as="h1" size="3xl" color="white" fontWeight="bold">
              Wizraven
            </Heading>
          </HStack>
          <Text fontSize="xl" color="whiteAlpha.900" maxW="md">
            AI-Powered Network Log Analysis with Cerebras Intelligence
          </Text>
          <HStack spacing={4}>
            <Badge colorScheme="green" px={3} py={1} borderRadius="full" fontSize="sm">
              <HStack spacing={1}>
                <Icon as={MdFlashOn} boxSize={3} />
                <Text>Fast Analysis</Text>
              </HStack>
            </Badge>
            <Badge colorScheme="blue" px={3} py={1} borderRadius="full" fontSize="sm">
              <HStack spacing={1}>
                <Icon as={MdSecurity} boxSize={3} />
                <Text>Secure Processing</Text>
              </HStack>
            </Badge>
          </HStack>
        </VStack>

        {/* Main Content */}
        <Box
          bg="white"
          borderRadius="2xl"
          boxShadow="2xl"
          p={8}
          backdropFilter="blur(10px)"
          border="1px solid"
          borderColor="whiteAlpha.200"
        >
          <Grid templateColumns={{ base: '1fr', lg: '400px 1fr' }} gap={8} alignItems="start">
            {/* Sidebar */}
            <GridItem>
              <VStack spacing={6} align="stretch">
                {/* Welcome Card */}
                <Box
                  p={6}
                  bg="linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)"
                  borderRadius="xl"
                  color="white"
                  position="sticky"
                  top="24px"
                >
                  <VStack spacing={4} align="start">
                    <HStack spacing={3}>
                      <Icon as={MdMemory} boxSize={6} />
                      <Heading as="h3" size="md">
                        Getting Started
                      </Heading>
                    </HStack>
                    <Text fontSize="sm" opacity={0.9}>
                      Enter your Cerebras API key and paste logs or questions to start analyzing with AI-powered insights.
                    </Text>
                  </VStack>
                </Box>

                {/* Features Card */}
                <Box p={6} bg="gray.50" borderRadius="xl" border="1px solid" borderColor="gray.200">
                  <Heading as="h4" size="sm" mb={4} color="gray.800">
                    Features
                  </Heading>
                  <VStack spacing={3} align="start">
                    <HStack spacing={3}>
                      <Box w={2} h={2} bg="green.400" borderRadius="full" />
                      <Text fontSize="sm" color="gray.600">Real-time log analysis</Text>
                    </HStack>
                    <HStack spacing={3}>
                      <Box w={2} h={2} bg="blue.400" borderRadius="full" />
                      <Text fontSize="sm" color="gray.600">Interactive conversations</Text>
                    </HStack>
                    <HStack spacing={3}>
                      <Box w={2} h={2} bg="purple.400" borderRadius="full" />
                      <Text fontSize="sm" color="gray.600">Knowledge base integration</Text>
                    </HStack>
                    <HStack spacing={3}>
                      <Box w={2} h={2} bg="orange.400" borderRadius="full" />
                      <Text fontSize="sm" color="gray.600">Security threat detection</Text>
                    </HStack>
                  </VStack>
                </Box>
              </VStack>
            </GridItem>

            {/* Main Chat Area */}
            <GridItem>
              <LogInput />
            </GridItem>
          </Grid>
        </Box>
      </Container>
    </Box>
  );
}