<?php
namespace DafDb\Migrations\Services;

interface ISqlExpression
{
    public function Signature(): string;
    public function Compile(string $providerId): string;
}

final class SqlExpression implements ISqlExpression
{
    private function __construct(
        private ?string $raw = null,
        private ?string $kind = null
    ) {}
    
    public const DATE_NOW = 'kind:date_now';
    public const DATE_TIME_NOW = 'kind:datetime_now';
    public const CURRENT_TIMESTAMP = 'kind:current_timestamp';
    public const UU_ID = 'kind:uuid';

    public static function FromSignature(string $sig): self {
        $sig = trim($sig);

        if (str_starts_with($sig, "kind:"))
            return new self(kind: substr($sig, 5));
        
        if (str_starts_with($sig, "raw:")) 
            $sig = substr($sig, 4);

        return new self(raw: $sig);
    }
    public function Signature(): string
    {
        if ($this->raw !== null) {
            // Canonicalize whitespace so formatting doesn’t create fake diffs
            $sql = preg_replace('/\s+/', ' ', trim($this->raw));
            return 'raw:' . $sql;
        }

        if ($this->kind === null || $this->kind === '') {
            throw new \InvalidArgumentException("SqlExpression kind and raw cannot be empty");
        }

        return 'kind:' . $this->kind;
    }

    public static function Raw(string $sql): self
    {
        return new self(raw: $sql);
    }

    public function Compile(string $providerId): string
    {
        if ($this->raw !== null) return $this->raw;

        return match ($this->kind) {
            'date_now' => match ($providerId) {
                'mysql'  => 'CURRENT_DATE',
                'sqlite' => "(DATE('now'))",
                'pgsql'  => 'CURRENT_DATE',
                default  => throw new \Exception("currentTimestamp not supported for $providerId"),
            },
            'datetime_now' => match ($providerId) {
                'mysql'  => 'CURRENT_TIMESTAMP',
                'sqlite' => "(datetime('now'))",
                'pgsql'  => 'CURRENT_TIMESTAMP',
                default  => throw new \Exception("currentTimestamp not supported for $providerId"),
            },
            'current_timestamp' => match ($providerId) {
                'mysql'  => 'CURRENT_TIMESTAMP',
                'sqlite' => "(datetime('now'))",
                'pgsql'  => 'CURRENT_TIMESTAMP',
                default  => throw new \Exception("currentTimestamp not supported for $providerId"),
            },
            'uuid' => match ($providerId) {
                'mysql'  => 'UUID()',
                'sqlite' => "(lower(hex(randomblob(16))))",
                'pgsql'  => 'gen_random_uuid()',
                default  => throw new \Exception("uuid not supported for $providerId"),
            },
            default => throw new \Exception("Unknown SqlExpr kind"),
        };
    }
}