<?php
namespace DafGlobals;

interface ICollection extends \IteratorAggregate, \ArrayAccess , \Countable, \JsonSerializable {
    function Add(mixed $item): void;
    function Remove(callable $callback) : void;


    /**
     * Removes all items from the collection
     */
    function Clear(): void;

    /**
     * @param int $length The number of items to skip
     * @return ICollection A new collection with the items skipped
     */
    function Skip(int $length): self;

    /**
     * @param int $length
     * @return ICollection A new collection with the items taken
     */
    function Take(int $length): self;

    /**
     * @param callable $callback A function to test each element for a condition
     * @return bool True if the collection is not empty
     */
    function Any(callable $callback = null): bool;

    /**
     * @param callable $callback A function to test each element for a condition
     * @return int The number of elements that satisfy the condition
     */
    function Count(callable $callback = null): int;

    /**
     * @param callable $callback A function to apply to each element
     * @return ICollection A new collection with the results of the function applied to each element
     */
    function Map(callable $callback): self;

    /**
     * @param callable $callback A function to apply to each element
     * @return ICollection A new collection with the elements that satisfy the condition
     */
    function ForEach(callable $callback) : void;

    /**
     * @param callable $callback A function to apply to each element
     * @return ICollection A new collection with the elements that satisfy the condition
     */
    function Reverse(): self;


    /**
     * @param callable $callback A function to test each element for a condition
     * @return mixed The first element that satisfies the condition
     */
    function FirstOrDefault(callable $callback = null): mixed;

    /**
     * @param callable $callback A function to test each element for a condition
     * @return mixed The last element that satisfies the condition
     * @throws \Exception If found more then one element
     */
    function SingleOrDefault(callable $callback = null): mixed;

    /**
     * @param callable $callback A function to test each element for a condition
     * @return ICollection A new collection with the elements that satisfy the condition
     */
    function Where(callable $callback) : self;

    /**
     * @param callable $callback A function to test each element for a condition
     * @return int|string|null The key of the first element that satisfies the condition
     */
    function FindKey(callable $callback) : int|string|null;

    /**
     * @return array The collection as an array
     */
    function ToArray(): array;
}