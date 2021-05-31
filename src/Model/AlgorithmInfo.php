<?php

namespace App\Model;

class AlgorithmInfo
{
    protected $version;
    protected $name;
    protected $className;
    protected $description;

    public function __construct(string $version, string $name, string $className, string $description)
    {
        $this->version = $version;
        $this->name = $name;
        $this->className = $className;
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }
}