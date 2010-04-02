<?php
$options = array(
  'title' => __('%friend% List', array('%friend%' => $op_term['friend']->titleize())),
  'list' => $friends,
  'link_to' => '@obj_member_profile?id=',
  'moreInfo' => array(link_to(sprintf('%s(%d)', __('Show all'), $member->countFriends()), '@friend_list?id='.$member->getId())),
  'type' => $sf_data->getRaw('gadget')->getConfig('type'),
  'row' => $row,
  'col' => $col,
);

if ($member->getId() == $sf_user->getMember()->getId())
{
  $options['moreInfo'][] = link_to(__('%my_friend% Setting', array(
    '%my_friend%' => $op_term['my_friend']->titleize()->pluralize(),
  )), '@friend_manage');
}

op_include_parts('nineTable', 'frendList_'.$gadget->getId(), $options);
