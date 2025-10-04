import { extendTheme } from '@chakra-ui/react';

// Modern gradient-friendly palette inspired by the Cerebras tester
const colors = {
  brand: {
    50: '#f0f4ff',
    100: '#e0e7ff',
    200: '#c7d2fe',
    300: '#a5b4fc',
    400: '#818cf8',
    500: '#6366f1',
    600: '#4f46e5',
    700: '#4338ca',
    800: '#3730a3',
    900: '#312e81',
  },
  accent: {
    50: '#fdf4ff',
    100: '#fae8ff',
    200: '#f5d0fe',
    300: '#f0abfc',
    400: '#e879f9',
    500: '#d946ef',
    600: '#c026d3',
    700: '#a21caf',
    800: '#86198f',
    900: '#701a75',
  },
  gradient: {
    primary: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    secondary: 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)',
    accent: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
  }
};

const theme = extendTheme({
  colors,
  fonts: {
    heading: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif",
    body: "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif",
  },
  styles: {
    global: {
      body: {
        bg: 'gray.50',
        color: 'gray.800',
      },
    },
  },
  components: {
    Button: {
      baseStyle: {
        borderRadius: 'lg',
        fontWeight: 'semibold',
      },
      variants: {
        solid: {
          bg: 'brand.500',
          color: 'white',
          _hover: { 
            bg: 'brand.600',
            transform: 'translateY(-1px)',
            boxShadow: 'lg',
          },
          _active: { 
            bg: 'brand.700',
            transform: 'translateY(0px)',
          },
          transition: 'all 0.2s',
        },
        ghost: {
          _hover: {
            bg: 'gray.100',
          },
        },
      },
      sizes: {
        md: {
          px: 6,
          py: 3,
        },
      },
    },
    Input: {
      variants: {
        outline: {
          field: {
            borderRadius: 'lg',
            _focus: {
              borderColor: 'brand.500',
              boxShadow: '0 0 0 1px var(--chakra-colors-brand-500)',
            },
          },
        },
      },
    },
    Textarea: {
      variants: {
        outline: {
          borderRadius: 'lg',
          _focus: {
            borderColor: 'brand.500',
            boxShadow: '0 0 0 1px var(--chakra-colors-brand-500)',
          },
        },
      },
    },
    Card: {
      baseStyle: {
        container: {
          borderRadius: 'xl',
          boxShadow: 'md',
        },
      },
    },
  },
});

export default theme;
