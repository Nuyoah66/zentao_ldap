<?php
class ldapModel extends model
{
    public function getUserDn($config, $account){
    $ret = null;
    $ds = ldap_connect($config->host);
    if ($ds) {
        ldap_set_option($ds,LDAP_OPT_PROTOCOL_VERSION,3);
        ldap_bind($ds, $config->bindDN, $config->bindPWD);
        $filter = "($config->uid=$account)";
        $rlt = ldap_search($ds, $config->baseDN, $filter);
        $count=ldap_count_entries($ds, $rlt);

        if($count > 0){
            $data = ldap_get_entries($ds, $rlt);
            $ret = $data[0]['dn'];
            $str = serialize($data);
        }

        ldap_unbind($ds);
        ldap_close($ds);
    }
    return $ret;
    }
    
    public function identify($host, $dn, $pwd)
    {
        $ret = '';
        $ds = ldap_connect($host);
        if ($ds) {
            ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_bind($ds, $dn, $pwd);

            $ret = ldap_error($ds);
            ldap_close($ds);
        } else {
            $ret = ldap_error($ds);
        }

        return $ret;
    }

    public function getUsers($config)
    {
        $ds = ldap_connect($config->host);
        if ($ds) {
            ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_bind($ds, $config->bindDN, $config->bindPWD);

            $attrs = [$config->uid, $config->mail, $config->name];

            $rlt = ldap_search($ds, $config->baseDN, $config->searchFilter, $attrs);
            $data = ldap_get_entries($ds, $rlt);
            return $data;
        }

        return null;
    }

    public function sync2db($config)
    {
        $ldapUsers = $this->getUsers($config);
        $user = new stdclass();
        $group = new stdclass();
        $account = '';
        $i=0;
        for (; $i < $ldapUsers['count']; $i++) {
            $user->account = $ldapUsers[$i][$config->uid][0];
            if (empty($ldapUsers[$i][$config->mail][0])) {
                $user->email = $user->account . '@test.ad';
            } else {
                $user->email = $ldapUsers[$i][$config->mail][0];
            }
            $user->realname = $ldapUsers[$i][$config->name][0];
            $group->group = (!empty($config->group) ? $config->group : $this->config->ldap->group);


            $account = $this->dao->select('*')->from(TABLE_USER)->where('account')->eq($user->account)->fetch('account');
            if ($account == $user->account) {
                $user->deleted = 0;
                $this->dao->update(TABLE_USER)->data($user)->where('account')->eq($user->account)->autoCheck()->exec();
            } else {
                $user->gender = 'm';
                $user->role = 'others';
                $this->dao->insert(TABLE_USER)->data($user)->autoCheck()->exec();
            }

            if (dao::isError()) {
                echo js::error(dao::getError());
                die(js::reload('parent'));
            }
        }

        return $i;
    }
}
