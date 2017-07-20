<?php
namespace Rguedes\LaravelWargamingAuth;

interface WargamingAuthInterface
{
    public function redirect();
    public function validate();
    public function getAuthUrl();
    public function getWargamingId();
    public function getWargamingToken();
    public function getUserInfo();
}