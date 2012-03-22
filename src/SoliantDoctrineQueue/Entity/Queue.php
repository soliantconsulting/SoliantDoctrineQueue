<?
/**
 * The queue table
 */

namespace SoliantDoctrineQueue\Entity;

class Queue
{
    protected $id;

    public function getId() {
        return $this->id;
    }

    public function setId($value) {
        $this->id = $value;
        return $this;
    }

    protected $name;

    public function getName() {
        return $this->name;
    }

    public function setName($value) {
        $this->name = $value;
        return $this;
    }

    protected $timeout = 30;

    public function getTimeout() {
        return $this->timeout;
    }

    public function setTimeout($value) {
        $this->timeout = $value;
        return $this;
    }

    protected $messages;

    public function getMessages() {
        return $this->messages;
    }

    public function toArray ()
    {
        return get_object_vars($this);
    }

}