declare module '*.css' {
  const content: Record<string, string>;
  export default content;
}

declare module '*.svg' {
  const content: string;
  export default content;
}

// Extend Window interface properly
declare global {
  interface Window {
    [key: string]: unknown;
  }
}

export {};