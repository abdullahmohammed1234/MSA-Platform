/**
 * Ask the server to revoke this session, then always clear local state.
 * Resolves to true only when the server confirmed the revocation.
 */
export async function signOut(revoke: () => Promise<unknown>, clearLocal: () => void): Promise<boolean> {
  let serverConfirmed = false;
  try {
    await revoke();
    serverConfirmed = true;
  } catch {
    // The server may be unreachable or the token already revoked; local state is cleared below.
  } finally {
    clearLocal();
  }
  return serverConfirmed;
}
