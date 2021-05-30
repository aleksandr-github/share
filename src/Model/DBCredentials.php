<?php

namespace App\Model;

class DBCredentials
{
    private $servername;
    private $username;
    private $password;
    private $database;

    /**
     * @return mixed
     */
    public function getServername(): string
    {
        return $this->servername;
    }

    /**
     * @param mixed $servername
     * @return DBCredentials
     */
    public function setServername(string $servername): DBCredentials
    {
        $this->servername = $servername;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param mixed $username
     * @return DBCredentials
     */
    public function setUsername(string $username): DBCredentials
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param mixed $password
     * @return DBCredentials
     */
    public function setPassword(string $password): DBCredentials
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDatabase(): string
    {
        return $this->database;
    }

    /**
     * @param mixed $database
     * @return DBCredentials
     */
    public function setDatabase(string $database): DBCredentials
    {
        $this->database = $database;
        return $this;
    }
}