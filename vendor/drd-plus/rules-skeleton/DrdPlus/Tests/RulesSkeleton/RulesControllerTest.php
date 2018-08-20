<?php
namespace DrdPlus\Tests\RulesSkeleton;

use DeviceDetector\Parser\Bot;
use DrdPlus\FrontendSkeleton\CookiesService;
use DrdPlus\FrontendSkeleton\HtmlDocument;
use DrdPlus\FrontendSkeleton\Redirect;
use DrdPlus\RulesSkeleton\Dirs;
use DrdPlus\RulesSkeleton\RulesController;
use DrdPlus\RulesSkeleton\Request;
use DrdPlus\RulesSkeleton\UsagePolicy;

class RulesControllerTest extends \DrdPlus\Tests\FrontendSkeleton\FrontendControllerTest
{
    use Partials\AbstractContentTestTrait;

    /**
     * @param string|null $documentRoot
     * @return \DrdPlus\FrontendSkeleton\Dirs|Dirs
     */
    protected function createDirs(string $documentRoot = null): \DrdPlus\FrontendSkeleton\Dirs
    {
        return new Dirs($this->getMasterDocumentRoot(), $documentRoot);
    }

    /**
     * @test
     */
    public function I_can_set_access_as_free_for_everyone(): void
    {
        $controller = new RulesController('Google Analytics ID foo', $this->createHtmlHelper(), new Dirs($this->getMasterDocumentRoot(), $this->getDocumentRoot()));
        self::assertFalse($controller->isFreeAccess(), 'Access should be protected by default');
        self::assertSame($controller, $controller->setFreeAccess());
        self::assertTrue($controller->isFreeAccess(), 'Access should be switched to free');
    }

    /**
     * @test
     */
    public function I_can_get_request(): void
    {
        $controller = new RulesController('Google Analytics ID foo', $this->createHtmlHelper(), new Dirs($this->getMasterDocumentRoot(), $this->getDocumentRoot()));
        self::assertEquals(new Request(new Bot()), $controller->getRequest());
    }

    /**
     * @test
     */
    public function I_can_get_usage_policy(): void
    {
        $controller = new RulesController('Google Analytics ID foo', $this->createHtmlHelper(), new Dirs($this->getMasterDocumentRoot(), $this->getDocumentRoot()));
        self::assertEquals(
            new UsagePolicy($this->getVariablePartOfNameForPass(), new Request(new Bot()), new CookiesService()),
            $controller->getUsagePolicy()
        );
    }

    /**
     * @test
     * @throws \ReflectionException
     */
    public function I_can_activate_trial(): void
    {
        $rulesController = new RulesController(
            'Google Analytics ID foo',
            $this->createHtmlHelper(),
            new Dirs($this->getMasterDocumentRoot(),
                $this->getDocumentRoot())
        );
        $now = new \DateTime();
        $trialExpectedExpiration = (clone $now)->modify('+4 minutes');
        $usagePolicy = $this->mockery(UsagePolicy::class);
        $usagePolicy->expects('getTrialExpiredAtName')
            ->atLeast()->once()
            ->andReturn('bar');
        $usagePolicy->expects('activateTrial')
            ->with($this->type(\DateTime::class))
            ->andReturnUsing(function (\DateTime $expiresAt) use ($trialExpectedExpiration) {
                self::assertEquals($trialExpectedExpiration, $expiresAt);

                return true;
            });
        $controllerReflection = new \ReflectionClass($rulesController);
        $usagePolicyProperty = $controllerReflection->getProperty('usagePolicy');
        $usagePolicyProperty->setAccessible(true);
        $usagePolicyProperty->setValue($rulesController, $usagePolicy);
        self::assertTrue($rulesController->activateTrial($now));
        $redirect = $rulesController->getRedirect();
        self::assertNotNull($redirect);
        $trialExpectedExpirationTimestamp = $trialExpectedExpiration->getTimestamp() + 1; // one second "insurance" overlap
        self::assertSame('/?bar=' . $trialExpectedExpirationTimestamp, $redirect->getTarget());
        self::assertSame($trialExpectedExpirationTimestamp - $now->getTimestamp(), $redirect->getAfterSeconds());
    }

    /**
     * @test
     * @backupGlobals enabled
     */
    public function I_will_be_redirected_via_html_meta_on_trial(): void
    {
        self::assertCount(0, $this->getMetaRefreshes($this->getHtmlDocument()), 'No meta tag with refresh meaning expected so far');
        $this->passOut();
        $controller = null;
        $now = \time();
        $trialExpiredAt = $now + 240 + 1;
        $trialExpiredAtSecondAfter = $trialExpiredAt++;
        if ($this->getTestsConfiguration()->hasProtectedAccess()) { // can be solved by POST
            $_POST['trial'] = 1;
        } else {
            $controller = $this->createController();
            $controller->setRedirect(new Redirect('/?' . UsagePolicy::TRIAL_EXPIRED_AT . '=' . $trialExpiredAt, 240));
        }
        $trialContent = $this->fetchNonCachedContent($controller);
        $document = new HtmlDocument($trialContent);
        $metaRefreshes = $this->getMetaRefreshes($document);
        self::assertCount(1, $metaRefreshes, 'One meta tag with refresh meaning expected');
        $metaRefresh = \current($metaRefreshes);
        self::assertRegExp(
            "~241; url=/[?]trialExpiredAt=($trialExpiredAt|$trialExpiredAtSecondAfter)~",
            $metaRefresh->getAttribute('content')
        );
    }

    /**
     * @test
     */
    public function I_can_get_cookies_service(): void
    {
        $controller = new RulesController('Google Anakytics ID foo', $this->createHtmlHelper(), new Dirs($this->getMasterDocumentRoot(), $this->getDocumentRoot()));
        self::assertEquals(new CookiesService(), $controller->getCookiesService());
    }
}