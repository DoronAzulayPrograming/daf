<?php
namespace DafGlobals;


/**
 * @implements ICollection<int, mixed>
 */
class Collection extends ReadOnlyCollection
{
    /**
     * @param mixed $item The item to add to the collection
     * @return void
     */
    function Add(mixed $item): void
    {
        if(is_array($item)){
            $this->list = array_merge($this->list, $item);
            return;
        }
        
        $this->list[] = $item;
    }

    /**
     * @param callable $callback A function to test each element for a condition
     * @return void
     */
    function Remove(callable $callback) : void{
        $this->list = array_filter($this->list ,function($item) use ($callback) {
            return !$callback($item);
        });
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