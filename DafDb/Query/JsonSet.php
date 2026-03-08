<?php
namespace DafDb\Query;

final class JsonSet
{
    private string $path;
    private string $className;

    /** @var array<int, array{op:string,item:mixed,match:callable|null}> */
    private array $pending = [];

    /** @var array<int, callable> */
    private array $filters = [];
    private int $skip = 0;
    private ?int $take = null;
    private bool $reverse = false;

    /** @var array<int, mixed>|null */
    private ?array $viewItems = null;
    private bool $isView = false;

    public function __construct(string $path, string $className = 'array')
    {
        $this->path = $path;
        $this->className = $className;
        $this->assertClassName();
    }

    public function Add(mixed $item): mixed
    {
        $this->ensureWritable();

        $this->pending[] = [
            'op' => 'add',
            'item' => $this->normalizeRow($item),
            'match' => null,
        ];

        return $item;
    }

    public function Update(mixed $item, callable $callback): void
    {
        $this->ensureWritable();

        $this->pending[] = [
            'op' => 'update',
            'item' => $this->normalizeRow($item),
            'match' => $callback,
        ];
    }

    public function Remove(callable $callback): void
    {
        $this->ensureWritable();

        $this->pending[] = [
            'op' => 'remove',
            'item' => null,
            'match' => $callback,
        ];
    }

    public function Clear(): void
    {
        if ($this->isView) {
            $this->viewItems = [];
            return;
        }

        $this->pending[] = [
            'op' => 'clear',
            'item' => null,
            'match' => null,
        ];
    }

    public function SaveChanges(): int
    {
        $this->ensureWritable();

        if (empty($this->pending)) {
            return 0;
        }

        $lockHandle = $this->acquireLock(LOCK_EX);
        try {
            $rows = $this->loadRowsWithoutLock();
            $affected = 0;

            foreach ($this->pending as $entry) {
                switch ($entry['op']) {
                    case 'add':
                        $rows[] = $entry['item'];
                        $affected++;
                        break;

                    case 'update':
                        $replace = $entry['item'];
                        $match = $entry['match'];

                        foreach ($rows as $idx => $row) {
                            $entity = $this->materializeRow($row);
                            if ($this->invokeMatch($match, $entity, $idx) === true) {
                                $rows[$idx] = $replace;
                                $affected++;
                            }
                        }
                        break;

                    case 'remove':
                        $match = $entry['match'];
                        $next = [];

                        foreach ($rows as $idx => $row) {
                            $entity = $this->materializeRow($row);
                            if ($this->invokeMatch($match, $entity, $idx) === true) {
                                $affected++;
                                continue;
                            }
                            $next[] = $row;
                        }

                        $rows = $next;
                        break;

                    case 'clear':
                        $affected += count($rows);
                        $rows = [];
                        break;
                }
            }

            $this->saveRowsWithoutLock($rows);
            $this->pending = [];

            return $affected;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    public function Skip(int $length): self
    {
        $next = clone $this;
        $next->skip = max(0, $next->skip + $length);
        return $next;
    }

    public function Take(int $length): self
    {
        $next = clone $this;
        $next->take = $length < 0 ? 0 : $length;
        return $next;
    }

    public function Any(?callable $callback = null): bool
    {
        if ($callback !== null) {
            return $this->Where($callback)->Count() > 0;
        }

        return $this->Count() > 0;
    }

    public function Count(?callable $callback = null): int
    {
        if ($callback !== null) {
            return $this->Where($callback)->Count();
        }

        return count($this->resolveQueryItems());
    }

    public function Map(callable $callback): self
    {
        $source = $this->resolveQueryItems();
        $mapped = [];

        foreach ($source as $key => $item) {
            $mapped[] = $this->invokeWithOptionalKey($callback, $item, $key);
        }

        return $this->asView($mapped);
    }

    public function ForEach(callable $callback): void
    {
        foreach ($this->resolveQueryItems() as $key => $item) {
            $this->invokeWithOptionalKey($callback, $item, $key);
        }
    }

    public function Reverse(): self
    {
        $next = clone $this;
        $next->reverse = !$next->reverse;
        return $next;
    }

    public function FirstOrDefault(?callable $callback = null): mixed
    {
        $items = $callback !== null ? $this->Where($callback)->resolveQueryItems() : $this->resolveQueryItems();
        $value = reset($items);
        return $value === false ? null : $value;
    }

    public function SingleOrDefault(?callable $callback = null): mixed
    {
        $items = $callback !== null ? $this->Where($callback)->resolveQueryItems() : $this->resolveQueryItems();
        if (count($items) > 1) {
            throw new \Exception("Sequence contains more than one element");
        }

        $value = reset($items);
        return $value === false ? null : $value;
    }

    public function Where(callable $callback): self
    {
        $next = clone $this;
        $next->filters[] = $callback;
        return $next;
    }

    public function ToArray(): array
    {
        return $this->resolveQueryItems(asArray: true);
    }

    public function FindKey(callable $callback): int|string|null
    {
        foreach ($this->resolveQueryItems() as $key => $item) {
            if ($this->invokeWithOptionalKey($callback, $item, $key) === true) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Allows query results to mimic Queryable::RowToArray style.
     */
    public function RowToArray(bool $value = true): self
    {
        $next = clone $this;
        if ($value) {
            $next->className = 'array';
        }
        return $next;
    }

    private function resolveQueryItems(bool $asArray = false): array
    {
        $source = $this->isView ? ($this->viewItems ?? []) : $this->loadCurrentItems();

        foreach ($this->filters as $filter) {
            $source = array_values(array_filter(
                $source,
                fn($item, $key) => $this->invokeWithOptionalKey($filter, $item, $key) === true,
                ARRAY_FILTER_USE_BOTH
            ));
        }

        if ($this->skip > 0) {
            $source = array_slice($source, $this->skip);
        }

        if ($this->take !== null) {
            $source = array_slice($source, 0, $this->take);
        }

        if ($this->reverse) {
            $source = array_reverse($source);
        }

        if ($asArray) {
            return array_values(array_map(fn($x) => $this->normalizeRow($x), $source));
        }

        return array_values($source);
    }

    private function loadCurrentItems(): array
    {
        $lockHandle = $this->acquireLock(LOCK_SH);
        try {
            $rows = $this->loadRowsWithoutLock();
            return array_map(fn(array $row) => $this->materializeRow($row), $rows);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function loadRowsWithoutLock(): array
    {
        $this->ensureFileInitialized();
        $raw = file_get_contents($this->path);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \Exception("JsonSet file must contain a JSON array: {$this->path}");
        }

        $rows = [];
        foreach ($decoded as $idx => $row) {
            if (!is_array($row)) {
                throw new \Exception("JsonSet row at index {$idx} must be an object/array");
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function saveRowsWithoutLock(array $rows): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \Exception("JsonSet failed to create directory: {$dir}");
        }

        $tmp = $this->path . '.' . uniqid('tmp', true);
        $json = json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \Exception("JsonSet failed to encode json: {$this->path}");
        }

        if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
            throw new \Exception("JsonSet failed to write temp file: {$tmp}");
        }

        if (!rename($tmp, $this->path)) {
            @unlink($tmp);
            throw new \Exception("JsonSet failed to replace file: {$this->path}");
        }
    }

    private function ensureFileInitialized(): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \Exception("JsonSet failed to create directory: {$dir}");
        }

        if (!file_exists($this->path)) {
            if (file_put_contents($this->path, "[]\n", LOCK_EX) === false) {
                throw new \Exception("JsonSet failed to initialize file: {$this->path}");
            }
        }
    }

    private function acquireLock(int $mode)
    {
        $lockPath = $this->path . '.lock';
        $dir = dirname($lockPath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \Exception("JsonSet failed to create lock directory: {$dir}");
        }

        $handle = fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new \Exception("JsonSet failed to open lock file: {$lockPath}");
        }

        if (!flock($handle, $mode)) {
            fclose($handle);
            throw new \Exception("JsonSet failed to lock file: {$lockPath}");
        }

        return $handle;
    }

    private function normalizeRow(mixed $item): array
    {
        if (is_array($item)) {
            return $item;
        }

        if (!is_object($item)) {
            throw new \InvalidArgumentException("JsonSet row must be object|array");
        }

        if ($item instanceof \JsonSerializable) {
            $serialized = $item->jsonSerialize();
            if (!is_array($serialized)) {
                throw new \InvalidArgumentException("JsonSet jsonSerialize() must return array for row objects");
            }
            return $serialized;
        }

        return get_object_vars($item);
    }

    private function materializeRow(array $row): mixed
    {
        if ($this->className === 'array') {
            return $row;
        }

        if ($this->className === 'stdClass' || $this->className === \stdClass::class) {
            return (object)$row;
        }

        $class = $this->className;
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("JsonSet class does not exist: {$class}");
        }

        try {
            return new $class($row);
        } catch (\Throwable) {
            try {
                $obj = new $class();
            } catch (\Throwable $e) {
                throw new \Exception("JsonSet failed to materialize {$class}", previous: $e);
            }

            foreach ($row as $key => $value) {
                try {
                    $obj->$key = $value;
                } catch (\Throwable) {
                    // ignore non-writable properties
                }
            }

            return $obj;
        }
    }

    private function asView(array $items): self
    {
        $next = clone $this;
        $next->isView = true;
        $next->viewItems = array_values($items);
        $next->pending = [];
        $next->filters = [];
        $next->skip = 0;
        $next->take = null;
        $next->reverse = false;
        return $next;
    }

    private function assertClassName(): void
    {
        if ($this->className === 'array' || $this->className === 'stdClass' || $this->className === \stdClass::class) {
            return;
        }

        if (!class_exists($this->className)) {
            throw new \InvalidArgumentException("JsonSet class does not exist: {$this->className}");
        }
    }

    private function invokeMatch(?callable $callback, mixed $item, int|string $key): bool
    {
        if ($callback === null) {
            return false;
        }

        return $this->invokeWithOptionalKey($callback, $item, $key) === true;
    }

    private function invokeWithOptionalKey(callable $callback, mixed $item, int|string $key): mixed
    {
        try {
            if (is_array($callback)) {
                $ref = new \ReflectionMethod($callback[0], $callback[1]);
            } elseif (is_string($callback) && str_contains($callback, '::')) {
                $ref = new \ReflectionMethod($callback);
            } else {
                $ref = new \ReflectionFunction(\Closure::fromCallable($callback));
            }

            if ($ref->getNumberOfParameters() >= 2) {
                return $callback($item, $key);
            }
        } catch (\Throwable) {
            // fallback below
        }

        return $callback($item);
    }

    private function ensureWritable(): void
    {
        if ($this->isView) {
            throw new \Exception("JsonSet write operations are not allowed on query views");
        }
    }
}
