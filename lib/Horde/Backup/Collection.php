<?php
/**
 * Copyright 2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Backup
 */

namespace Horde\Backup;

use IteratorIterator;
use Traversable;

/**
 * A collection of backup objects of a certain application-specific type.
 *
 * Each application should extend this class for each type of backup data it
 * supports.
 *
 * @author    Jan Schneider <jan@horde.org>
 * @category  Horde
 * @copyright 2017 Horde LLC
 * @license   http://www.horde.org/licenses/bsd BSD
 * @package   Backup
 */
abstract class Collection extends IteratorIterator
{
    /**
     * The collection's user.
     *
     * @var string
     */
    protected $_user;

    /**
     * Constructor.
     *
     * @param string $user  A user name.
     */
    public function __construct(Traversable $iterator, $user)
    {
        parent::__construct($iterator);
        $this->_user = $user;
    }

    /**
     * Returns the type of objects or resources that this collection holds.
     *
     * @return string  The collection type.
     */
    abstract public function getType();
}