<?php
declare(strict_types=1);
/** be strict for parameter types, https://www.quora.com/Are-strict_types-in-PHP-7-not-a-bad-idea */

namespace DrdPlus\RulesSkeleton\Exceptions;

class ArticleNameCanNotBeEmptyForUsagePolicy extends \LogicException implements Logic
{

}