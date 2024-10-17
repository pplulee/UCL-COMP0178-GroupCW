<?php

namespace model;

class MFACredential
{
    public int $id;
    public int $userid;
    public string $body;
    public string|null $name;
    public string|null $rawid;
    public string $created_at;
    public string|null $used_at;
    public string $type;

    public function __construct()
    {
        $this->id = 0;
        $this->userid = 0;
        $this->body = '';
        $this->name = null;
        $this->rawid = null;
        $this->created_at = '';
        $this->used_at = null;
        $this->type = '';
    }

    public function save(): bool
    {
        global $conn;
        // Save the MFA credential
        // Insert if id is 0, otherwise update
        if ($this->id === 0) {
            // Insert
            $stmt = $conn->prepare('INSERT INTO `mfa_credential` (`userid`, `body`, `name`, `rawid`, `created_at`, `used_at`, `type`) VALUES (:user_id, :body, :name, :rawid, :created_at, :used_at, :type)');
            return $stmt->execute([
                'user_id' => $this->userid,
                'body' => $this->body,
                'name' => $this->name,
                'rawid' => $this->rawid,
                'created_at' => $this->created_at,
                'used_at' => $this->used_at,
                'type' => $this->type
            ]);
        } else {
            // Update
            $stmt = $conn->prepare('UPDATE `mfa_credential` SET `userid` = :user_id, `body` = :body, `name` = :name, `rawid` = :rawid, `created_at` = :created_at, `used_at` = :used_at, `type` = :type WHERE `id` = :id');
            return $stmt->execute([
                'user_id' => $this->userid,
                'body' => $this->body,
                'name' => $this->name,
                'rawid' => $this->rawid,
                'created_at' => $this->created_at,
                'used_at' => $this->used_at,
                'type' => $this->type,
                'id' => $this->id
            ]);
        }
    }

}