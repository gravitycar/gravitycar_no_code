/**
 * Returns the post-login navigation target from a ?redirect= query param.
 * Accepts only relative paths (starting with /) to prevent open redirect attacks.
 * Rejects protocol-relative URLs (starting with //) that could redirect off-site.
 */
export function getRedirectPath(searchParams: URLSearchParams): string {
  const redirect = searchParams.get('redirect');
  if (redirect && redirect.startsWith('/') && !redirect.startsWith('//')) {
    return redirect;
  }
  return '/';
}
