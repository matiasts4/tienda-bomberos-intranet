import { NextResponse } from "next/server"
import type { NextRequest } from "next/server"

// Since the frontend (intranettb.com) and backend (different domain) are on separate domains,
// server-side cookie auth in middleware cannot work (backend session cookies are not visible here).
// Authentication for internal ERP is enforced client-side in each page via useAuthContext().
// Authentication for /m/* (marketplace) is enforced via useMarketplaceAuth().

export function middleware(req: NextRequest) {
  const { pathname } = req.nextUrl

  // Marketplace routes are completely public-facing and managed by marketplace tokens.
  // Never intercept or redirect marketplace paths.
  if (pathname.startsWith('/m/') || pathname === '/m') {
    return NextResponse.next()
  }

  return NextResponse.next()
}

export const config = {
  matcher: [
    /*
     * Match all request paths except:
     * - _next/static, _next/image (Next.js internals)
     * - favicon.ico
     */
    "/((?!_next/static|_next/image|favicon\\.ico).*)",
  ],
}
