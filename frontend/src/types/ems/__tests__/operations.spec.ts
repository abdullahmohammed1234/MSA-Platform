import { describe, expect, it } from 'vitest';
import { extractTicketCode } from '@/types/ems/operations';

describe('extractTicketCode', () => {
  it('returns uppercase bare codes', () => {
    expect(extractTicketCode('msa-abc123')).toBe('MSA-ABC123');
  });

  it('extracts the code from a ticket URL', () => {
    expect(extractTicketCode('https://msa.test/tickets/MSA-7K9MQ2X4P8')).toBe('MSA-7K9MQ2X4P8');
  });

  it('strips query strings', () => {
    expect(extractTicketCode('/tickets/MSA-HELLO?x=1')).toBe('MSA-HELLO');
  });
});
