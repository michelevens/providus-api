<?php

namespace App\Http\Middleware;

use App\Models\Agency;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmbedCors
{
    /**
     * Allow cross-origin requests for public embed widget endpoints.
     *
     * Validates the Origin header against the agency's allowed_domains.
     * If no allowed_domains are set, allows all origins (open embed).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');

        // Handle preflight OPTIONS
        if ($request->isMethod('OPTIONS')) {
            return $this->addCorsHeaders(response('', 204), $origin, $request);
        }

        $response = $next($request);

        return $this->addCorsHeaders($response, $origin, $request);
    }

    private function addCorsHeaders($response, ?string $origin, Request $request)
    {
        if (!$origin) {
            return $response;
        }

        // Extract slug from route parameter
        $slug = $request->route('slug');

        if ($slug) {
            $agency = Agency::where('slug', $slug)->first();

            // If agency has allowed_domains set, validate origin
            if ($agency && !empty($agency->allowed_domains)) {
                $originHost = parse_url($origin, PHP_URL_HOST);
                $allowed = false;

                foreach ($agency->allowed_domains as $domain) {
                    $domain = trim($domain);
                    if ($originHost === $domain || str_ends_with($originHost, '.' . $domain)) {
                        $allowed = true;
                        break;
                    }
                }

                if (!$allowed) {
                    return $response;
                }
            }
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
}
