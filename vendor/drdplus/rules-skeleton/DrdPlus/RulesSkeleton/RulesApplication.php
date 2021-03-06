<?php declare(strict_types=1);

namespace DrdPlus\RulesSkeleton;

use DrdPlus\RulesSkeleton\Web\RulesContent;
use Granam\Strict\Object\StrictObject;
use Granam\WebContentBuilder\Web\Exceptions\UnknownWebFilesDir;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class RulesApplication extends StrictObject
{
    /** @var ServicesContainer */
    private $servicesContainer;
    /** @var Configuration */
    private $configuration;
    /** @var RulesContent */
    private $content;
    /** @var Redirect */
    private $redirect;
    /** @var bool */
    private $canPassIn;
    /** @var RulesContent */
    private $notFoundContent;

    public function __construct(ServicesContainer $servicesContainer)
    {
        $this->servicesContainer = $servicesContainer;
        $this->configuration = $servicesContainer->getConfiguration();
    }

    public function run(): void
    {
        try {
            $this->sendCustomHeaders();
            if ($this->isRequestedWebVersionUpdate()) {
                echo $this->updateCode();
                $this->clearCache();
            } else {
                $this->persistCurrentVersion();
                echo $this->getContent()->getValue();
            }
        } catch (UnknownWebFilesDir | RouteNotFoundException | ResourceNotFoundException $invalidRoute) {
            $this->sendNotFoundHeaders();
            echo $this->getNotFoundContent()->getValue();
        }
    }

    private function isRequestedWebVersionUpdate(): bool
    {
        return $this->servicesContainer->getRequest()->getValue(Request::UPDATE) === 'web';
    }

    protected function updateCode(): string
    {
        return \implode(
            "\n",
            $this->servicesContainer->getGit()->update($this->servicesContainer->getDirs()->getProjectRoot())
        );
    }

    protected function clearCache()
    {
        $this->servicesContainer->getCacheCleaner()->clearCache();
    }

    private function persistCurrentVersion(): bool
    {
        return $this->servicesContainer->getCookiesService()->setMinorVersionCookie(
            $this->servicesContainer->getCurrentWebVersion()->getCurrentMinorVersion()
        );
    }

    private function getContent(): RulesContent
    {
        if ($this->content) {
            return $this->content;
        }
        $servicesContainer = $this->servicesContainer;
        if ($servicesContainer->getTablesRequestDetector()->areTablesRequested()) {
            $this->content = new RulesContent(
                $servicesContainer->getTablesContent(),
                $servicesContainer->getMenu(),
                $servicesContainer->getCurrentWebVersion(),
                $servicesContainer->getTablesWebCache(),
                RulesContent::TABLES,
                $this->getRedirect()
            );

            return $this->content;
        }
        if ($servicesContainer->getRequest()->isRequestedPdf() && $servicesContainer->getRoutedWebPartsContainer()->getPdfBody()->getPdfFile()) {
            $this->content = new RulesContent(
                $servicesContainer->getPdfContent(),
                $servicesContainer->getEmptyMenu(),
                $servicesContainer->getCurrentWebVersion(),
                $servicesContainer->getDummyWebCache(),
                RulesContent::PDF,
                $this->getRedirect()
            );

            return $this->content;
        }
        if (!$this->canPassIn()) {
            $this->content = new RulesContent(
                $servicesContainer->getPassContent(),
                $servicesContainer->getMenu(),
                $servicesContainer->getCurrentWebVersion(),
                $servicesContainer->getPassWebCache(),
                RulesContent::PASS,
                $this->getRedirect()
            );

            return $this->content;
        }
        $this->content = new RulesContent(
            $servicesContainer->getRulesMainContent(),
            $servicesContainer->getMenu(),
            $servicesContainer->getCurrentWebVersion(),
            $servicesContainer->getPassedWebCache(),
            RulesContent::FULL,
            $this->getRedirect()
        );

        return $this->content;
    }

    private function getRedirect(): ?Redirect
    {
        return $this->redirect;
    }

    private function canPassIn(): bool
    {
        if ($this->canPassIn !== null) {
            return $this->canPassIn;
        }
        $canPassIn = !$this->configuration->hasProtectedAccess();
        if (!$canPassIn) {
            $usagePolicy = $this->servicesContainer->getUsagePolicy();
            $canPassIn = $usagePolicy->isVisitorBot();
            if (!$canPassIn) {
                $canPassIn = $usagePolicy->hasVisitorConfirmedOwnership();
                if (!$canPassIn) {
                    $canPassIn = $usagePolicy->isVisitorUsingValidTrial();
                    if (!$canPassIn) {
                        if ($this->servicesContainer->getRequest()->getValueFromPost(Request::CONFIRM)) {
                            /** @noinspection PhpUnhandledExceptionInspection */
                            $canPassIn = $usagePolicy->confirmOwnershipOfVisitor(new \DateTime('+1 year'));
                        }
                        if (!$canPassIn && $this->servicesContainer->getRequest()->getValue(Request::TRIAL)) {
                            $canPassIn = $this->activateTrial($this->servicesContainer->getNow());
                        }
                    }
                }
            }
        }

        return $this->canPassIn = $canPassIn;
    }

    private function activateTrial(\DateTimeImmutable $now): bool
    {
        $trialExpiration = $now->modify('+4 minutes');
        $visitorCanAccessContent = $this->servicesContainer->getUsagePolicy()->activateTrial($trialExpiration);
        if ($visitorCanAccessContent) {
            $at = $trialExpiration->getTimestamp() + 1; // one second "insurance" overlap
            $afterSeconds = $at - $now->getTimestamp();
            $this->setRedirect(new Redirect(\sprintf('/?%s=%d', Request::TRIAL_EXPIRED_AT, $at), $afterSeconds));
        }

        return $visitorCanAccessContent;
    }

    private function setRedirect(Redirect $redirect): void
    {
        $this->redirect = $redirect;
        $this->content = null; // unset Content to re-create it with new redirect
    }

    private function sendCustomHeaders(): void
    {
        if ($this->getContent()->containsTables()) {
            if ($this->servicesContainer->getRequest()->isCliRequest()) {
                return;
            }
            // anyone can show content of this page
            \header('Access-Control-Allow-Origin: *');
        } elseif ($this->getContent()->containsPdf()) {
            $pdfFile = $this->servicesContainer->getRoutedWebPartsContainer()->getPdfBody()->getPdfFile();
            $pdfFileBasename = \basename($pdfFile);
            if ($this->servicesContainer->getRequest()->isCliRequest()) {
                return;
            }
            \header('Content-type: application/pdf');
            \header('Content-Length: ' . \filesize($pdfFile));
            \header("Content-Disposition: attachment; filename=\"$pdfFileBasename\"");
        }
    }

    private function sendNotFoundHeaders(): void
    {
        if ($this->servicesContainer->getRequest()->isCliRequest()) {
            return;
        }
        http_response_code(404);
    }

    private function getNotFoundContent(): RulesContent
    {
        if ($this->notFoundContent) {
            return $this->notFoundContent;
        }
        $servicesContainer = $this->servicesContainer;
        $this->notFoundContent = new RulesContent(
            $servicesContainer->getNotFoundContent(),
            $servicesContainer->getMenu(),
            $servicesContainer->getCurrentWebVersion(),
            $servicesContainer->getNotFoundCache(),
            RulesContent::NOT_FOUND,
            $this->getRedirect()
        );

        return $this->notFoundContent;
    }
}