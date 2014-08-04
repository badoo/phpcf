<?php
class Test
{
    /**
     * Set user id
     * 
     * @param int $user_id User id
     * @return Folder_Section_Entry_SectionUser
     */
    public function setUserId($user_id)
    {
        return $this->_set('uid', $user_id, self::TYPE_INT);
    }

    /**
     * Get user id
     * 
     * @return int
     */
    public function getUserId()
    {
        return $this->_get('uid');
    }
}
