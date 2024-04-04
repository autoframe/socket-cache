<?php

namespace Autoframe\Components\SocketCache\App;

use ArrayAccess;
use Autoframe\DesignPatterns\Singleton\AfrSingletonAbstractClass;
use Countable;
use Iterator;


abstract class ArrayAccessSingletonApp extends AfrSingletonAbstractClass implements ArrayAccess, Iterator, Countable
{

    protected array $aArrayAccessData = [];
    protected array $aArrayAccessKeys = [];      //We use a separate array of keys rather than $this->position directly so that we can
    protected int $iArrayAccessPointer = 0;            //have an associative array.

    protected function keyMaping(bool $bResetPointer): void
    {
        $this->iArrayAccessPointer = 0;
        $this->aArrayAccessKeys = array_keys($this->aArrayAccessData);
    }


    public function count(): int
    { //This is necessary for the Countable interface. It could as easily return
        return count($this->aArrayAccessKeys);    //count($this->container). The number of elements will be the same.
    }

    public function rewind(): void
    {  //Necessary for the Iterator interface. $this->position shows where we are in our list of
        $this->iArrayAccessPointer = 0;      //keys. Remember we want everything done via $this->keys to handle associative arrays.
    }

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function current()
    { //Necessary for the Iterator interface.
        return $this->aArrayAccessData[$this->aArrayAccessKeys[$this->iArrayAccessPointer]];
    }

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function key()
    { //Necessary for the Iterator interface.
        return $this->aArrayAccessKeys[$this->iArrayAccessPointer];
    }

    public function next(): void
    { //Necessary for the Iterator interface.
        ++$this->iArrayAccessPointer;
    }

    public function valid(): bool
    { //Necessary for the Iterator interface.
        return isset($this->aArrayAccessKeys[$this->iArrayAccessPointer]);
    }

    public function offsetSet($offset, $value): void
    { //Necessary for the ArrayAccess interface.
        if (is_null($offset)) {
            $this->aArrayAccessData[] = $value;
            $this->aArrayAccessKeys[] = array_key_last($this->aArrayAccessData); //THIS IS ONLY VALID FROM php 7.3 ONWARDS. See note below for alternative.
        } else {
            $this->aArrayAccessData[$offset] = $value;
            if (!in_array($offset, $this->aArrayAccessKeys)) $this->aArrayAccessKeys[] = $offset;
        }
    }

    public function offsetExists($offset): bool
    {
        return isset($this->aArrayAccessData[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->aArrayAccessData[$offset]);
        $this->keyMaping(false);
        //This line re-indexes the array of container keys because if someone
    }

    /**
     * @param $offset
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function &offsetGet($offset)
    {
        return $this->aArrayAccessData[$offset];
    }

    public function &__get($key)
    {
        return $this->aArrayAccessData[$key];
    }

    public function __set($key, $value)
    {
        $this->aArrayAccessData[$key] = $value;
    }

    public function __isset($key)
    {
        return isset($this->aArrayAccessData[$key]);
    }

    public function __unset($key)
    {
        unset($this->aArrayAccessData[$key]);
    }


}