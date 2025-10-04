import { Box, Container, Heading, Text, Spinner } from '@chakra-ui/react';
import { useRouter } from 'next/router';
import { useEffect, useState } from 'react';

interface AnalysisResult {
  summary: string;
  recommendations: string[];
  details: {
    category: string;
    findings: string[];
  }[];
}

export default function Results() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [result, setResult] = useState<AnalysisResult | null>(null);

  useEffect(() => {
    // TODO: Implement API call to fetch analysis results
    // This is a placeholder for demonstration
    const fetchResults = async () => {
      try {
        // For demo purposes, generate results from the stored log content
        const logContent = sessionStorage.getItem('demoLogContent') || '';
        
        // Generate a simple demo analysis
        const demoResult: AnalysisResult = {
          summary: `Analyzed log content (${logContent.length} characters)`,
          recommendations: [
            'Review system performance metrics',
            'Check for error patterns',
            'Monitor resource usage'
          ],
          details: [
            {
              category: 'Performance',
              findings: [
                'System response time: Normal',
                'Resource utilization: Within limits'
              ]
            },
            {
              category: 'Errors',
              findings: [
                'No critical errors detected',
                'Standard log patterns observed'
              ]
            }
          ]
        };
        
        setResult(demoResult);
        setLoading(false);
      } catch (error) {
        console.error('Error fetching results:', error);
        setLoading(false);
      }
    };

    if (router.query.id) {
      fetchResults();
    }
  }, [router.query.id]);

  if (loading) {
    return (
      <Container centerContent>
        <Spinner size="xl" mt={20} />
        <Text mt={4}>Analyzing logs...</Text>
      </Container>
    );
  }

  return (
    <Container maxW="container.xl" py={8}>
      <Heading as="h1" mb={6}>Analysis Results</Heading>
      {result && (
        <Box>
          <Box mb={8}>
            <Heading as="h2" size="lg" mb={4}>Summary</Heading>
            <Text>{result.summary}</Text>
          </Box>

          <Box mb={8}>
            <Heading as="h2" size="lg" mb={4}>Recommendations</Heading>
            {result.recommendations.map((rec, index) => (
              <Text key={index} mb={2}>• {rec}</Text>
            ))}
          </Box>

          {result.details.map((section, index) => (
            <Box key={index} mb={8}>
              <Heading as="h2" size="lg" mb={4}>{section.category}</Heading>
              {section.findings.map((finding, fidx) => (
                <Text key={fidx} mb={2}>• {finding}</Text>
              ))}
            </Box>
          ))}
        </Box>
      )}
      {result && (
        <Box>
          <Box mb={6}>
            <Heading as="h2" size="md" mb={3}>Summary</Heading>
            <Text>{result.summary}</Text>
          </Box>

          <Box mb={6}>
            <Heading as="h2" size="md" mb={3}>Recommendations</Heading>
            {result.recommendations.map((rec, index) => (
              <Text key={index} mb={2}>• {rec}</Text>
            ))}
          </Box>

          <Box>
            <Heading as="h2" size="md" mb={3}>Detailed Findings</Heading>
            {result.details.map((section, index) => (
              <Box key={index} mb={4}>
                <Heading as="h3" size="sm" mb={2}>{section.category}</Heading>
                {section.findings.map((finding, idx) => (
                  <Text key={idx} mb={2}>• {finding}</Text>
                ))}
              </Box>
            ))}
          </Box>
        </Box>
      )}
    </Container>
  );
}