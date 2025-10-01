import { Box, Container, Heading } from '@chakra-ui/react';
import LogInput from '../components/LogInput';

export default function Home() {
  return (
    <Container maxW="container.xl" py={8}>
      <Box textAlign="center" mb={8}>
        <Heading as="h1" size="2xl" mb={2}>
          Wizraven
        </Heading>
        <Heading as="h2" size="md" color="gray.600" fontWeight="normal">
          AI-Powered Network Log Analysis
        </Heading>
      </Box>
      <LogInput />
    </Container>
  );
}