import { describe, it, expect } from 'vitest';
import { useLandingData } from './useLandingData';

describe('useLandingData', () => {
  it('should return data when data is provided', () => {
    const data = { name: 'Real Data', value: 42 };
    const fallback = { name: 'Fallback Data', value: 0 };

    const result = useLandingData(data, fallback);

    expect(result).toBe(data);
    expect(result).not.toBe(fallback);
  });

  it('should return fallback when data is undefined', () => {
    const data = undefined;
    const fallback = { name: 'Fallback Data', value: 0 };

    const result = useLandingData(data, fallback);

    expect(result).toBe(fallback);
  });

  it('should return fallback when data is null', () => {
    const data = null;
    const fallback = { name: 'Fallback Data', value: 0 };

    const result = useLandingData(data as any, fallback);

    expect(result).toBe(fallback);
  });

  it('should return data when data is an empty object', () => {
    const data = {};
    const fallback = { name: 'Fallback Data', value: 0 };

    const result = useLandingData(data, fallback);

    expect(result).toBe(data);
    expect(result).not.toBe(fallback);
  });

  it('should return data when data is an empty array', () => {
    const data: any[] = [];
    const fallback = [{ name: 'Fallback Item' }];

    const result = useLandingData(data, fallback);

    expect(result).toBe(data);
    expect(result).not.toBe(fallback);
  });

  it('should return data when data is false', () => {
    const data = false;
    const fallback = true;

    const result = useLandingData(data, fallback);

    expect(result).toBe(data);
    expect(result).not.toBe(fallback);
  });

  it('should return data when data is 0', () => {
    const data = 0;
    const fallback = 42;

    const result = useLandingData(data, fallback);

    expect(result).toBe(data);
    expect(result).not.toBe(fallback);
  });

  it('should return data when data is empty string', () => {
    const data = '';
    const fallback = 'Fallback String';

    const result = useLandingData(data, fallback);

    expect(result).toBe(data);
    expect(result).not.toBe(fallback);
  });

  it('should work with complex nested objects', () => {
    const data = {
      testimonials: [
        { id: '1', quote: 'Real testimonial', author: { name: 'John' } },
        { id: '2', quote: 'Another real testimonial', author: { name: 'Jane' } },
      ],
      contextGroups: {
        casamento: [],
        assessoria: [],
        corporativo: [],
      },
    };

    const fallback = {
      testimonials: [
        { id: 'fallback-1', quote: 'Fallback testimonial', author: { name: 'Fallback' } },
      ],
      contextGroups: {
        casamento: [],
        assessoria: [],
        corporativo: [],
      },
    };

    const result = useLandingData(data, fallback);

    expect(result).toBe(data);
    expect(result.testimonials).toHaveLength(2);
    expect(result.testimonials[0].author.name).toBe('John');
  });

  it('should work with arrays', () => {
    const data = [1, 2, 3];
    const fallback = [0];

    const result = useLandingData(data, fallback);

    expect(result).toBe(data);
    expect(result).toHaveLength(3);
  });

  it('should preserve type safety', () => {
    type TestData = {
      name: string;
      count: number;
    };

    const data: TestData = { name: 'Real', count: 5 };
    const fallback: TestData = { name: 'Fallback', count: 0 };

    const result: TestData = useLandingData(data, fallback);

    expect(result.name).toBe('Real');
    expect(result.count).toBe(5);
  });
});
