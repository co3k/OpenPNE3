<?php

if (!function_exists('azure_getconfig')) {
    echo 'Please install php_azure.dll before executing this script.'.PHP_EOL;

    exit(1);
}

try {
    $dsn = sprintf('sqlsrv:Server=%s; Database=%s;', azure_getconfig('OPENPNE_DB_HOST'), azure_getconfig('OPENPNE_DB_NAME'));
    $pdo = new PDO($dsn, azure_getconfig('OPENPNE_DB_USER'), azure_getconfig('OPENPNE_DB_PASSWORD'));
} catch (PDOException $e) {
    echo 'Connecting to database was failed with the following error(s): '.PHP_EOL.$e->getMessage().PHP_EOL;

    exit(1);
}

// Current OpenPNE lacks to build some triggers for member deletion. This sql covers such triggers.

$sql = 'ALTER TRIGGER [member_cascade_delete_trigger] ON [member] INSTEAD OF DELETE AS BEGIN'
     .' DELETE FROM [album] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [album_image] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' UPDATE [ashiato] SET member_id_o = NULL WHERE member_id_to IN (SELECT d.id FROM [deleted] AS d);'
     .' UPDATE [ashiato] SET member_id_to = NULL WHERE member_id_to IN (SELECT d.id FROM [deleted] AS d);'
     .' UPDATE [community_topic] SET member_id = NULL WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' UPDATE [community_topic_comment] SET member_id = NULL WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' UPDATE [community_event] SET member_id = NULL WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' UPDATE [community_event_comment] SET member_id = NULL WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [community_event_member] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [diary] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' UPDATE [diary_comment] SET member_id = NULL WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [diary_comment_unread] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [diary_comment_update] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [favorite] WHERE member_id_to IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [intro_friend] WHERE member_id_to IN (SELECT d.id FROM [deleted] AS d);'
     .' UPDATE [message] SET member_id = NULL WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [favorite] WHERE member_id_from IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [intro_friend] WHERE member_id_from IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [intro_friend] WHERE member_id_to IN (SELECT d.id FROM [deleted] AS d);'
     .' UPDATE [message] SET member_id = NULL WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' UPDATE [message_send_list] SET member_id = NULL WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [deleted_message] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' UPDATE [application] SET member_id = NULL WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [member_application] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [application_invite] WHERE to_member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [application_persistent_data] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [application_lifecycle_event_queue] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [member_relationship] WHERE member_id_from IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [member_relationship] WHERE member_id_to IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [member_image] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [member_profile] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [community_member] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [community_member_position] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [member_config] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [oauth_consumer] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [o_auth_member_token] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [openid_trust_log] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [activity_data] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [member_application] WHERE member_id IN (SELECT d.id FROM [deleted] AS d);'
     .' DELETE FROM [member] WHERE  EXISTS (SELECT d.id FROM [deleted] AS d WHERE [member].id = d.id);'
     .' END';

$stmt = $pdo->prepare($sql);
$stmt->execute();
