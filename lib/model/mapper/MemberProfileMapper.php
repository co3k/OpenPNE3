<?php

class MemberProfileMapper extends opDoctrineSimpleRecord
{
  protected $profile = null;

  public function isViewable($memberId = null)
  {
    if (is_null($memberId))
    {
      $memberId = sfContext::getInstance()->getUser()->getMemberId();
    }

    switch ($this->public_flag)
    {
      case ProfileTable::PUBLIC_FLAG_FRIEND:
        $relation = Doctrine::getTable('MemberRelationship')->retrieveByFromAndTo($this->member_id, $memberId);
        if  ($relation && $relation->isFriend())
        {
          return true;
        }

        return ($this->member_id == $memberId);

      case ProfileTable::PUBLIC_FLAG_PRIVATE:
        return false;

      case ProfileTable::PUBLIC_FLAG_SNS:
        return (bool)$memberId;

      case ProfileTable::PUBLIC_FLAG_WEB:
        return ($this->getProfile()->is_public_web) ? true : (bool)$memberId;
    }
  }

  public function getPublicFlag()
  {
    $profile = $this->getProfile();
    if ($profile->is_edit_public_flag)
    {
      return $this->public_flag ? $this->public_flag : $profile->is_edit_public_flag;
    }

    return $profile->is_edit_public_flag;
  }

  // あとで共通化
  public function getProfile()
  {
    if (!$this->profile)
    {
      $this->profile = Doctrine::getTable('Profile')->find($this->profile_id, 'mapper');
    }

    return $this->profile;
  }
}
