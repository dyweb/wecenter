<?php
/*
+--------------------------------------------------------------------------
|   WeCenter [#RELEASE_VERSION#]
|   ========================================
|   by WeCenter Software
|   © 2011 - 2014 WeCenter. All Rights Reserved
|   http://www.wecenter.com
|   ========================================
|   Support: WeCenter@qq.com
|
+---------------------------------------------------------------------------
*/

if (!defined('IN_ANWSION'))
{
    die;
}

class openid_gitlab extends AWS_CONTROLLER
{
    public function get_access_rule()
    {
        $rule_action['rule_type'] = 'white';

        $rule_action['actions'] = array(
            'bind'
        );

        return $rule_action;
    }

    public function setup()
    {
        HTTP::no_cache_header();

        if (get_setting('gitlab_login_enabled') != 'Y' OR !get_setting('gitlab_client_id') OR !get_setting('gitlab_client_secret'))
        {
            H::redirect_msg(AWS_APP::lang()->_t('本站未开通 GitLab 登录'), '/');
        }
    }

    public function bind_action()
    {
        if (AWS_APP::session()->gitlab_user)
        {
            $gitlab_user_info = AWS_APP::session()->gitlab_user;

            unset(AWS_APP::session()->gitlab_user);
        }

        if ($_GET['error'] == 'access_denied')
        {
            H::redirect_msg(AWS_APP::lang()->_t('授权失败'), '/account/login/');
        }

        if ($this->user_id)
        {
            $gitlab_user = $this->model('openid_gitlab')->get_gitlab_user_by_uid($this->user_id);

            if ($gitlab_user)
            {
                H::redirect_msg(AWS_APP::lang()->_t('此账号已绑定 GitLab 账号'), '/account/login/');
            }
        }

        if ($_GET['code'])
        {
            if ($_GET['code'] != $gitlab_user_info['authorization_code'])
            {
                $this->model('openid_gitlab')->authorization_code = $_GET['code'];

                $this->model('openid_gitlab')->redirect_url = '/account/openid/gitlab/bind/';

                if (!$this->model('openid_gitlab')->oauth2_login())
                {
                    H::redirect_msg($this->model('openid_gitlab')->error_msg, '/account/login/');
                }

                $gitlab_user_info = $this->model('openid_gitlab')->user_info;
            }

            if (!$gitlab_user_info)
            {
                H::redirect_msg(AWS_APP::lang()->_t('GitLab 登录失败，用户信息不存在'), '/account/login/');
            }

            $gitlab_user = $this->model('openid_gitlab')->get_gitlab_user_by_id($gitlab_user_info['id']);

            if ($this->user_id)
            {
                if ($gitlab_user)
                {
                    H::redirect_msg(AWS_APP::lang()->_t('此 GitLab 账号已被绑定'), '/account/login/');
                }

                $this->model('openid_gitlab')->bind_account($gitlab_user_info, $this->user_id);

                if (!$this->model('integral')->fetch_log($this->user_id, 'BIND_OPENID'))
                {
                    $this->model('integral')->process($this->user_id, 'BIND_OPENID', round((get_setting('integral_system_config_profile') * 0.2)), '绑定 OPEN ID');
                }

                HTTP::redirect('/account/setting/openid/');
            }
            else
            {
                if ($gitlab_user)
                {
                    $user = $this->model('account')->get_user_info_by_uid($gitlab_user['uid']);

                    if (!$user)
                    {
                        $this->model('openid_gitlab')->unbind_account($gitlab_user['uid']);

                        H::redirect_msg(AWS_APP::lang()->_t('本地用户不存在'), '/account/login/');
                    }

                    $this->model('openid_gitlab')->update_user_info($gitlab_user['id'], $gitlab_user_info);

                    if (get_setting('register_valid_type') == 'approval' AND $user['group_id'] == 3)
                    {
                        $redirect_url = '/account/valid_approval/';
                    }
                    else
                    {
                        if ($_GET['state'])
                        {
                            $state = base64_url_decode($_GET['state']);
                        }

                        if (get_setting('ucenter_enabled') == 'Y')
                        {
                            $redirect_url = '/account/sync_login/';

                            if ($state['return_url'])
                            {
                                $redirect_url .= 'url-' . base64_encode($state['return_url']);
                            }
                        }
                        else if ($state['return_url'])
                        {
                            $redirect_url = $state['return_url'];
                        }
                        else
                        {
                            $redirect_url = '/';
                        }

                        HTTP::set_cookie('_user_login', get_login_cookie_hash($user['user_name'], $user['password'], $user['salt'], $user['uid'], false));

                        if (get_setting('register_valid_type') == 'email' AND !$user['valid_email'])
                        {
                            AWS_APP::session()->valid_email = $user['email'];
                        }
                    }

                    HTTP::redirect($redirect_url);
                }
                else
                {
                    switch (get_setting('register_type'))
                    {
                        case 'close':
                            H::redirect_msg(AWS_APP::lang()->_t('本站目前关闭注册'), '/account/login/');

                            break;

                        case 'invite':
                            if (get_setting('gitlab_register_enabled') !== 'Y') {
                                H::redirect_msg(AWS_APP::lang()->_t('本站只能通过邀请注册'), '/account/login/');
                            }

                            break;

                        case 'weixin':
                            H::redirect_msg(AWS_APP::lang()->_t('本站只能通过微信注册'), '/account/login/');

                            break;
                    }

                    AWS_APP::session()->gitlab_user = $gitlab_user_info;

                    $this->crumb(AWS_APP::lang()->_t('完善资料'), '/account/login/');

                    TPL::assign('register_url', 'account/ajax/gitlab/register/');

                    TPL::assign('user_name', AWS_APP::session()->gitlab_user['name']);

                    TPL::assign('email', AWS_APP::session()->gitlab_user['email']);

                    TPL::import_css('css/register.css');

                    TPL::output('account/openid/callback');
                }
            }
        }
        else
        {
            $state = ($_GET['return_url']) ? base64_url_encode(array('return_url' => base64_decode($_GET['return_url']))) : null;

            HTTP::redirect($this->model('openid_gitlab')->get_redirect_url('/account/openid/gitlab/bind/', $state));
        }
    }

    public function unbind_action()
    {
        $this->model('openid_gitlab')->unbind_account($this->user_id);

        HTTP::redirect('/account/setting/openid/');
    }
}
