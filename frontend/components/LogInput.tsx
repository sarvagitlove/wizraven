import { useState } from 'react';
import { useRouter } from 'next/router';
import {
  Box,
  Button,
  FormControl,
  FormLabel,
  Textarea,
  VStack,
  useToast,
} from '@chakra-ui/react';

const LogInput = () => {
  const [logContent, setLogContent] = useState('');
  const router = useRouter();
  const toast = useToast();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!logContent.trim()) {
      toast({
        title: 'Error',
        description: 'Please enter log content',
        status: 'error',
        duration: 3000,
        isClosable: true,
      });
      return;
    }

    try {
      // TODO: Implement API call to submit logs
      // const response = await fetch('/api/analyze', {
      //   method: 'POST',
      //   headers: { 'Content-Type': 'application/json' },
      //   body: JSON.stringify({ content: logContent }),
      // });
      // const data = await response.json();
      // router.push(`/results?id=${data.analysisId}`);
      
      // Temporary placeholder navigation
      router.push('/results?id=demo');
    } catch (error) {
      toast({
        title: 'Error',
        description: 'Failed to submit logs for analysis',
        status: 'error',
        duration: 5000,
        isClosable: true,
      });
    }
  };

  return (
    <Box as="form" onSubmit={handleSubmit}>
      <VStack spacing={4}>
        <FormControl isRequired>
          <FormLabel>Network Logs</FormLabel>
          <Textarea
            value={logContent}
            onChange={(e) => setLogContent(e.target.value)}
            placeholder="Paste your network logs here..."
            minHeight="300px"
            resize="vertical"
          />
        </FormControl>
        <Button
          type="submit"
          colorScheme="blue"
          size="lg"
          width="full"
          isDisabled={!logContent.trim()}
        >
          Analyze Logs
        </Button>
      </VStack>
    </Box>
  );
};

export default LogInput;