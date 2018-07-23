<?php
global $testsConfiguration;
$testsConfiguration = new \DrdPlus\Tests\RulesSkeleton\TestsConfiguration('http://drdplus.loc', 'https://drdplus.info');
$testsConfiguration->disableHasTables();
$testsConfiguration->disableHasAuthors();
$testsConfiguration->disableHasProtectedAccess();
$testsConfiguration->disableCanBeBoughtOnEshop();
$testsConfiguration->disableHasCharacterSheet();
$testsConfiguration->disableHasLinksToJournals();
$testsConfiguration->disableHasLinkToSingleJournal();
$testsConfiguration->disableHasDebugContacts();
$testsConfiguration->disableHasIntroduction();
$testsConfiguration->disableHasCustomBodyContent();
$testsConfiguration->setBlockNamesToExpectedContent([]);
$testsConfiguration->setExpectedWebName('DrD+ pravidla a odkazy');
$testsConfiguration->setExpectedPageTitle('📚 DrD+ pravidla a odkazy');
$testsConfiguration->disableHasMoreVersions();
$testsConfiguration->disableHasLocalLinks();
$testsConfiguration->setExpectedGoogleAnalyticsId('UA-121206931-1');