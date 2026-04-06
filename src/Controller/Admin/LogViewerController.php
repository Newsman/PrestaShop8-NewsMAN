<?php
/**
 * Copyright © Dazoot Software S.R.L. All rights reserved.
 *
 * @author Newsman by Dazoot <support@newsman.com>
 * @copyright Copyright © Dazoot Software S.R.L. All rights reserved.
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * @website https://www.newsman.ro/
 */

declare(strict_types=1);

namespace PrestaShop\Module\Newsmanv8\Controller\Admin;

use PrestaShop\Module\Newsmanv8\Config;
use PrestaShop\Module\Newsmanv8\Util\LogFileReader;
use PrestaShop\Module\Newsmanv8\Util\Version;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

class LogViewerController extends FrameworkBundleAdminController
{
    private const MAX_LINES = 2000;
    private const DEFAULT_LINES = 500;

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request): Response
    {
        /** @var Config $config */
        $config = $this->get(Config::class);
        /** @var LogFileReader $logFileReader */
        $logFileReader = $this->get(LogFileReader::class);

        if (!$config->hasApiAccess()) {
            return $this->redirectToRoute('newsmanv8_oauth_step1');
        }

        $logFileReader->cleanOldLogs();
        $logFiles = $logFileReader->getFiles();

        $selectedFile = $request->query->get('file', '');
        $lines = min(
            max((int) $request->query->get('lines', self::DEFAULT_LINES), 1),
            self::MAX_LINES
        );

        if (empty($selectedFile) && !empty($logFiles)) {
            $selectedFile = $logFiles[0];
        }

        $logContent = [];
        $totalLines = 0;
        $fileSize = 0;

        if (!empty($selectedFile) && $logFileReader->isValidFilename($selectedFile)) {
            $fileSize = $logFileReader->getFileSize($selectedFile);
            [$logContent, $totalLines] = $logFileReader->readTail($selectedFile, $lines);
        }

        return $this->render('@Modules/newsmanv8/views/templates/admin/log_viewer.html.twig', [
            'logFiles' => $logFiles,
            'selectedFile' => $selectedFile,
            'logContent' => $logContent,
            'totalLines' => $totalLines,
            'displayedLines' => count($logContent),
            'fileSize' => $fileSize,
            'linesParam' => $lines,
            'moduleVersion' => Version::getModuleVersion(),
            'enableSidebar' => true,
            'help_link' => false,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function deleteAction(Request $request): RedirectResponse
    {
        /** @var LogFileReader $logFileReader */
        $logFileReader = $this->get(LogFileReader::class);
        $file = $request->request->get('file', '');

        if (!empty($file) && $logFileReader->isValidFilename($file)) {
            if ($logFileReader->deleteFile($file)) {
                $this->addFlash('success', sprintf('Log file "%s" deleted.', $file));
            } else {
                $this->addFlash('error', sprintf('Could not delete log file "%s".', $file));
            }
        }

        return $this->redirectToRoute('newsmanv8_log_viewer');
    }
}
