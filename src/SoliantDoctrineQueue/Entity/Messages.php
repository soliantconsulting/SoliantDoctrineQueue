<?
/**
 * The queue messages tables
 */
namespace SoliantDoctrineQueue\Entity;

class Messages
{

    protected $id;

    public function getId() {
        return $this->id;
    }

    public function setId($value) {
        $this->id = $value;
        return $this;
    }

    protected $queue;

    public function getQueue() {
        return $this->queue;
    }

    public function setQueue(Queue $value) {
        $this->queue = $value;
        return $this;
    }

    protected $handle;

    public function getHandle() {
        return $this->handle;
    }

    public function setHandle($value) {
        $this->handle = $value;
        return $this;
    }

    protected $body;

    public function getBody() {
        return $this->body;
    }

    public function setBody($value) {
        $this->body = $value;
        return $this;
    }

    protected $md5;

    public function getMd5() {
        return $this->md5;
    }

    public function setMd5($value) {
        $this->md5 = $value;
        return $this;
    }

    protected $timeout;

    public function getTimeout() {
        return $this->timeout;
    }

    public function setTimeout($value) {
        $this->timeout = $value;
        return $this;
    }

    protected $created;

    public function getCreated() {
        return $this->created;
    }

    public function setCreated($value) {
        $this->created = $value;
        return $this;
    }

    public function toArray ()
    {
        return get_object_vars($this);
    }

}