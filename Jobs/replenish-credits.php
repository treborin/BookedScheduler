<?php
/**
 * Copyright 2020 Nick Korbel
 *
 * This file is part of Booked Scheduler.
 *
 * Booked Scheduler is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Booked Scheduler is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Booked Scheduler.  If not, see <http://www.gnu.org/licenses/>.
 */

//////////////////
/* Cron Example //
//////////////////

This script must be executed every day to enable credit replenishment functionality

0 0 * * * php /home/mydomain/public_html/booked/Jobs/replenish-credits.php
0 0 * * * /path/to/php /home/mydomain/public_html/booked/Jobs/replenish-credits.php

*/

define('ROOT_DIR', dirname(__FILE__) . '/../');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Jobs/JobCop.php');

const JOB_NAME = 'replenish-credits';

Log::Debug('Running %s', JOB_NAME);

JobCop::EnsureCommandLine();
JobCop::EnforceSchedule(JOB_NAME, 1440);

try {
	if(!Configuration::Instance()->GetSectionKey(ConfigSection::CREDITS, ConfigKeys::CREDITS_ENABLED, new BooleanConverter())) {
		Log::Error('%s exiting. Credits not enabled.', JOB_NAME);
        JobCop::UpdateLastRun(JOB_NAME, false);
		return;
	}

    $groupRepository = new GroupRepository();
    $replenishmentRules = $groupRepository->GetAllReplenishmentRules();

    /** @var GroupCreditReplenishmentRule $rule */
    foreach ($replenishmentRules as $rule) {
        if ($rule->ShouldBeRunOn(Date::Now())) {
            Log::Debug("Credit replenishment rule running. Id=%s", $rule->Id());

            $groupRepository->AddCreditsToUsers($rule->GroupId(), $rule->Amount(), Resources::GetInstance()->GetString("AutoReplenishCreditsNot"));
            $rule->UpdateLastReplenishment(Date::Now());
            $groupRepository->UpdateReplenishmentRule($rule);
        }
        else {
            Log::Debug("Credit replenishment rule skipped. Id=%s", $rule->Id());
        }
    }

    JobCop::UpdateLastRun(JOB_NAME, true);
} catch (Exception $ex) {
    Log::Error('Error running %s: %s', JOB_NAME, $ex);
    JobCop::UpdateLastRun(JOB_NAME, false);
}

Log::Debug('Finished running %s', JOB_NAME);