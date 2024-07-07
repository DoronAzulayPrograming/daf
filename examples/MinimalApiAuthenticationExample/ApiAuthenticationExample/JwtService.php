<?php
namespace ApiAuthenticationExample;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private $issuer = "https://example.com"; // your issuer URL
    private $audience = "https://example-client.com"; // your audience URL
    private $secret_key; // your secret key for JWT
    private $algorithm; // your secret key for JWT
    private $expiration = 3600; // expiration time for JWT token (1 hour)
    private $last_expiration_time = 0;

    public function __construct($jwt_secret_key, $jwt_algorithm)
    {
      $this->secret_key = $jwt_secret_key;
      $this->algorithm = $jwt_algorithm;
    }

    public function GetLastExpirationTime(): int
    {
        return $this->last_expiration_time;
    }

    public function GenerateToken($claims)
    {
        // set expiration time for JWT token
        $issuedAt = time();
        $this->last_expiration_time = $expiresAt = $issuedAt + $this->expiration;

        // generate JWT token with user ID as payload
        $payload = array(
            "iss" => $this->issuer,
            "aud" => $this->audience,
            "iat" => $issuedAt,
            "exp" => $expiresAt,
            "claims" => $claims
        );

        $jwt = JWT::encode($payload, $this->secret_key, $this->algorithm);

        // return JWT token to client
        return $jwt;
    }

    public function ValidateToken($jwt): mixed
    {
        try {
            // decode JWT token
            $decoded = JWT::decode($jwt, new Key($this->secret_key, $this->algorithm));
            // check issuer, audience, and expiration time
            if ($decoded->iss !== $this->issuer || $decoded->aud !== $this->audience || $decoded->exp < time()) {
                return false;
            }

            // return claims
            return $decoded->claims;
        } catch (\Throwable $e) {
            // invalid JWT token
            return false;
        }
    }
}