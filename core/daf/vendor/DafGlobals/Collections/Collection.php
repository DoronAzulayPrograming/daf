<?php
namespace DafGlobals\Collections;


/**
 * @extends ReadOnlyCollection<int, mixed>
 */
class Collection extends ReadOnlyCollection
{
    public function __construct(array $list = [])
    {
        parent::__construct($list);
        $this->isMutable = true;
    }
    /**
     * @param mixed $item The item to add to the collection
     * @return mixed
     */
    function Add(mixed $item): mixed
    {
        if(is_array($item)){
            $this->list = array_values(array_merge($this->list, $item));
            return $item;
        }
        
        $this->list[] = $item;
        return $item;
    }

    /**
     * @param callable $callback A function to test each element for a condition
     * @return void
     */
    function Remove(callable $callback) : void{
        $filterd = array_filter($this->list ,function($item) use ($callback) {
            return !$callback($item);
        });
        $this->list = array_values($filterd);
    }
    function Clear(): void
    {
        $this->list = [];
    }

    function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->list[] = $value;
        } else {
            $this->list[$offset] = $value;
        }
    }
    function offsetUnset($offset): void
    {
        unset($this->list[$offset]);
    }

}