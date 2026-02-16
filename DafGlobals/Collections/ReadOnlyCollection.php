<?php
namespace DafGlobals\Collections;

class ReadOnlyCollection implements ICollection
{
    /** @var mixed[] $list */
    protected array $list = [];
    protected bool $isMutable = false;

    public function __construct(array $list = [])
    {
        $this->list = $list;
    }
    protected function newCollection(array $items): ICollection
    {
        return $this->isMutable ? new Collection($items) : new ReadOnlyCollection($items);
    }
    /**
     * @param mixed $item
     * @throws \Exception This collection is read only
     */
    public function Add(mixed $item): mixed
    {
        throw new \Exception("This collection is read only");
    }

    /**
     * @param callable $callback
     * @throws \Exception This collection is read only
     */
    public function Remove(callable $callback): void
    {
        throw new \Exception("This collection is read only");
    }

    /**
     * @throws \Exception This collection is read only
     */
    function Clear(): void
    {
        throw new \Exception("This collection is read only");
    }


    function Skip(int $length): ICollection
    {
        return $this->newCollection(array_slice($this->list, $length));
    }
    function Take(int $length): ICollection
    {
        return $this->newCollection(array_slice($this->list, 0, $length));
    }
    function Any(callable $callback = null): bool
    {   
        if($callback !== null){
            foreach ($this->list as $key => $item) {
                if ($callback($item, $key) === true) {
                    return true;
                }
            }
            return false;
        }
        
        return !empty($this->list);
    }
    function Count(callable $callback = null): int
    {
        if($callback !== null){
            return $this->Where($callback)->Count();
        }

        return count($this->list);
    }
    function Map(callable $callback): ICollection
    {
        $list = [];
        foreach ($this->list as $key => $item) {
            $list[] = $callback($item, $key);
        }
        return $this->newCollection($list);
    }
    function ForEach(callable $callback) : void
    {
        foreach ($this->list as $item) {
            $callback($item);
        }
    }
    function Reverse(): ICollection
    {
        return $this->newCollection(array_reverse($this->list));
    }

    function FirstOrDefault(callable $callback = null): mixed
    {
        if ($callback === null) {
            $value = reset($this->list);
            return $value === false ? null : $value;
        }

        $list = [];
        foreach ($this->list as $item) {
            if ($callback($item) === true) {
                $list[] = $item;
                break;
            }
        }

        $value = reset($list);
        return $value === false ? null : $value;
    }
    function SingleOrDefault(callable $callback = null): mixed
    {
        if ($callback === null) {
            $value = reset($this->list);
            return $value === false ? null : $value;
        }

        $list = [];
        foreach ($this->list as $item) {
            if ($callback($item) === true) {
                $list[] = $item;
            }
            if (count($list) > 1)
                break;
        }

        if (count($list) > 1) {
            throw new \Exception("Sequence contains more than one element");
        }

        $value = reset($list);
        return $value === false ? null : $value;
    }
    function LastOrDefault(callable $callback = null): mixed
    {
        if ($callback === null) {
            $value = end($this->list);
            return $value === false ? null : $value;
        }

        $list = [];
        foreach ($this->list as $item) {
            if ($callback($item) === true) {
                $list[] = $item;
            }
        }

        $value = end($list);
        return $value === false ? null : $value;
    }
    function Where(callable $callback): ICollection
    {
        $list = array_filter($this->list, $callback, ARRAY_FILTER_USE_BOTH);
        return $this->newCollection($list);
    }


    function FindKey(callable $callback): int|string|null
    {
        foreach ($this->list as $key => $item) {
            if ($callback($item, $key) === true) {
                return $key;
            }
        }
        return null;
    }


    function ToArray(): array
    {
        return $this->list;
    }



    function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->list);
    }

    function offsetSet($offset, $value): void
    {
        throw new \Exception("This collection is read only");
    }
    function offsetExists($offset): bool
    {
        return isset($this->list[$offset]);
    }
    function offsetUnset($offset): void
    {
        throw new \Exception("This collection is read only");
    }
    function offsetGet(mixed $offset): mixed
    {
        return isset($this->list[$offset]) ? $this->list[$offset] : null;
    }

    function __toString()
    {
        return json_encode($this->list);
    }
    function __debugInfo()
    {
        return $this->list;
    }
    function jsonSerialize()
    {
        return $this->list;
    }
}