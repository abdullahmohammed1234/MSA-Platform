/**
 * Keep the browser credential aligned with a password-change response.
 * The API deletes the token that was used for the request and returns a new one.
 * An empty value must not wipe a still-valid credential.
 */
export function applyRenewedAccessToken(
  storage: Pick<Storage, 'setItem'>,
  key: string,
  renewed: string | null | undefined,
): boolean {
  if (typeof renewed !== 'string' || renewed.length === 0) {
    return false;
  }

  storage.setItem(key, renewed);
  return true;
}
