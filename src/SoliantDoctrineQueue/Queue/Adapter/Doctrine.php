<?php
/**
 * @see Zend_Queue_Adapter_AdapterAbstract
 */
namespace SoliantDoctrineQueue\Queue\Adapter;

use Zend\Queue\Adapter\AbstractAdapter,
    Zend\Queue\Message,
    Zend\Queue\Queue;

/**
 * A Doctrine2-based queue adapter
 *
 * @author Tom Anderson <tanderson@soliantconsulting.com>
 * @comment Comments copied from Zend_Queue_Adapter_Db
 */
class Doctrine extends AbstractAdapter
{
    protected $em;

    public function setEm($em) {
        $this->em = $em;
        return $this;
    }

    /********************************************************************
     * Queue management functions
     *********************************************************************/

    /**
     * Does a queue already exist?
     *
     * Throws an exception if the adapter cannot determine if a queue exists.
     * use isSupported('isExists') to determine if an adapter can test for
     * queue existance.
     *
     * @param  string $name
     * @return boolean
     * @throws Zend_Queue_Exception
     */
    public function isExists($name)
    {
        if (!$this->em) throw \Exception('You must call setEm() before using this adapter');

        $repo = $this->em->getRepository('SoliantDoctrineQueue\Entity\Queue')->findOneBy(array(
            'name' => $name
        ));

        return ($repo) ? true: false;
    }

    /**
     * Create a new queue
     *
     * Visibility timeout is how long a message is left in the queue "invisible"
     * to other readers.  If the message is acknowleged (deleted) before the
     * timeout, then the message is deleted.  However, if the timeout expires
     * then the message will be made available to other queue readers.
     *
     * @param  string  $name    queue name
     * @param  integer $timeout default visibility timeout
     * @return boolean
     * @throws Zend_Queue_Exception - database error
     */
    public function create($name, $timeout = null)
    {
        if (!$this->em) throw \Exception('You must call setEm() before using this adapter');

        if ($this->isExists($name)) {
            return false;
        }

        $queue = new \SoliantDoctrineQueue\Entity\Queue;
        $queue->setName($name);
        $newtimeout = ($timeout === null) ? self::CREATE_TIMEOUT_DEFAULT : (int)$timeout;
        $queue->setTimeout($newtimeout);

        $this->em->persist($queue);
        $this->em->flush();

        return true;
    }

    /**
     * Delete a queue and all of it's messages
     *
     * Returns false if the queue is not found, true if the queue exists
     *
     * @param  string  $name queue name
     * @return boolean
     * @throws Zend_Queue_Exception - database error
     */
    public function delete($name)
    {
        if (!$this->em) throw \Exception('You must call setEm() before using this adapter');

        $id = $this->getQueueId($name); // get primary key

        $repo = $this->em->getRepository('SoliantDoctrineQueue\Entity\Queue')->find($id);

        foreach ($repo->messages as $message) {
            $this->em->remove($message);
        }

        $this->em->remove($repo);
        $this->em->flush();

        return true;
    }

    /*
     * Get an array of all available queues
     *
     * Not all adapters support getQueues(), use isSupported('getQueues')
     * to determine if the adapter supports this feature.
     *
     * @return array
     */
    public function getQueues()
    {
        if (!$this->em) throw \Exception('You must call setEm() before using this adapter');

        $queues = $this->em->getRepository('SoliantDoctrineQueue\Entity\Queue')->findAll();
        foreach ($queues as $queue) {
            $list[] = $queue->name;
        }

        return $list;
    }

    /**
     * Return the approximate number of messages in the queue
     *
     * @param  Zend_Queue $queue
     * @return integer
     * @throws Zend_Queue_Exception
     */
    public function count(Queue $queue = null)
    {
        if (!$this->em) throw \Exception('You must call setEm() before using this adapter');

        return (int)$this->em->getRepository('SoliantDoctrineQueue\Entity\Queue')->find($this->getQueueId($queue->getName()))->count();
    }

    /********************************************************************
    * Messsage management functions
     *********************************************************************/

    /**
     * Send a message to the queue
     *
     * @param  string     $message Message to send to the active queue
     * @param  Zend_Queue $queue
     * @return Zend_Queue_Message
     * @throws Zend_Queue_Exception - database error
     */
    public function send($message, Queue $queue = null)
    {
        if (!$this->em) throw \Exception('You must call setEm() before using this adapter');

        if ($queue === null) {
            $queue = $this->_queue;
        }

        if (is_scalar($message)) {
            $message = (string) $message;
        }
        if (is_string($message)) {
            $message = trim($message);
        }

        if (!$this->isExists($queue->getName())) {
            throw new \Exception('Queue does not exist:' . $queue->getName());
        }

        $msg = new \SoliantDoctrineQueue\Entity\Messages;
        $msg->setQueue($this->em->getRepository('SoliantDoctrineQueue\Entity\Queue')->find($this->getQueueId($queue->getName())));
        $msg->setCreated(time());
        $msg->setBody($message);
        $msg->setMd5(md5($message));

        $this->em->persist($msg);
        $this->em->flush();

        $options = array(
            'queue' => $queue,
            'data'  => $msg->toArray(),
        );

        $classname = $queue->getMessageClass();

        return new $classname($options);
    }

    /**
     * Get messages in the queue
     *
     * @param  integer    $maxMessages  Maximum number of messages to return
     * @param  integer    $timeout      Visibility timeout for these messages
     * @param  Zend_Queue $queue
     * @return Zend_Queue_Message_Iterator
     * @throws Zend_Queue_Exception - database error
     */
    public function receive($maxMessages = null, $timeout = null, Queue $queue = null)
    {
        if ($maxMessages === null) {
            $maxMessages = 1;
        }
        if ($timeout === null) {
            $timeout = self::RECEIVE_TIMEOUT_DEFAULT;
        }
        if ($queue === null) {
            $queue = $this->_queue;
        }

        $msgs = array();

        if ($maxMessages > 0) {
            $microtime = microtime(true); // cache microtime

            $queueEntity = $this->em->getRepository('SoliantDoctrineQueue\Entity\Queue')->find($this->getQueueId($queue->getName()));

            // Search for all messages inside our timeout
            $query = $this->em->createQuery("
                SELECT m
                FROM SoliantDoctrineQueue\Entity\Messages m
                WHERE (m.queue = :queue_key)
                AND (m.handle is null OR m.handle = '' OR m.timeout + " . (int)$timeout . " < " . (int)$microtime . ")
            ");
            $query->setParameter('queue_key', $queueEntity->getId());
            $query->setMaxResults($maxMessages);
            $messages = $query->getResult();

            // Update working messages
            foreach ($messages as $message) {
                $message->setHandle(md5(uniqid(rand(), true)));
                $message->setTimeout($microtime);

                $msgs[] = $message->toArray();
            }
            $this->em->flush();
        }

        $options = array(
            'queue'        => $queue,
            'data'         => $msgs,
            'messageClass' => $queue->getMessageClass(),
        );

        $classname = $queue->getMessageSetClass();
        return new $classname($options);
    }

    /**
     * Delete a message from the queue
     *
     * Returns true if the message is deleted, false if the deletion is
     * unsuccessful.
     *
     * @param  Zend_Queue_Message $message
     * @return boolean
     * @throws Zend_Queue_Exception - database error
     */
    public function deleteMessage(Message $message)
    {
        if (!$this->em) throw \Exception('You must call setEm() before using this adapter');

        $repo = $this->em->getRepository('SoliantDoctrineQueue\Entity\Messages')->findOneBy(array(
            'handle' => $message->handle
        ));

        $this->em->remove($repo);
        $this->em->flush();

        return true;
    }

    /********************************************************************
     * Supporting functions
     *********************************************************************/

    /**
     * Return a list of queue capabilities functions
     *
     * $array['function name'] = true or false
     * true is supported, false is not supported.
     *
     * @param  string $name
     * @return array
     */
    public function getCapabilities()
    {
        return array(
            'create'        => true,
            'delete'        => true,
            'send'          => true,
            'receive'       => true,
            'deleteMessage' => true,
            'getQueues'     => true,
            'count'         => true,
            'isExists'      => true,
        );
    }

    /********************************************************************
     * Functions that are not part of the Zend_Queue_Adapter_Abstract
     *********************************************************************/
    /**
     * Get the queue ID
     *
     * Returns the queue's row identifier.
     *
     * @param  string       $name
     * @return integer|null
     * @throws Zend_Queue_Exception
     */
    protected function getQueueId($name)
    {
        $repo = $this->em->getRepository('SoliantDoctrineQueue\Entity\Queue')->findOneBy(array(
            'name' => $name
        ));

        if (!$repo) throw new \Exception('Queue does not exist: ' . $name);

        return $repo->getId();
    }
}
