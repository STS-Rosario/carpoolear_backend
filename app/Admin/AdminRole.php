<?php

namespace STS\Admin;

enum AdminRole: string
{
    case Superadmin = 'superadmin';
    case Helpdesk = 'helpdesk';
}
