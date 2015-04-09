<?php

namespace SiteBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use SiteBundle\DocUtils;
use SiteBundle\ZipUtils;

class PublicController extends Controller
{
    
    
    private $_rootPath;
    
    private function getRootDirectories()
    {
        $rootPath = $this->getRootPath();
        // get root directories
        $files = DocUtils::scandir2($rootPath);
        $result = array();
        foreach($files as $f)
        {
            if (is_dir($rootPath.'/'.$f))
            {
                array_push($result, 
                        array( 
                            "name" => $f, 
                            "url" => '/'.DocUtils::slugify($f)));
            }
        }
        return $result;
    }
    
    private function getRootPath()
    {
        if (!isset($this->_rootPath))
        {
            $_rootPath = $this->container->getParameter('directory');
        }
        
        return $_rootPath;
    }
    
    /**
     * @Route("/debug")
     */
    public function debugAction() {
        return $this->render('SiteBundle:Default:debug.html.twig', array());
        
        $site = $this->container->getParameter('directory');
        
        $response = new Response(
                ' root: '.$this->getRootPath().
                ' post_max_size: '.ini_get('post_max_size').
                ' upload_max_filesize: '.ini_get('upload_max_filesize'));
        return $response;
    }
    
    /**
    * @Route("/{url}", requirements={"url":".*"})
     */
    public function indexAction(Request $request) {
        
        $uri = $request->getPathInfo();
        
        $parts = explode('/', $uri);
        
        $currentPath = $this->getRootPath();
        $relativePath = "";
        $error = false;
        
        foreach ($parts as $part) {
            
            if ($part === "")
            {
                continue;
            }
            
            $found = DocUtils::getDirFromUrl($currentPath, $part);
            if (isset($found))
            {
                $currentPath = $currentPath.'/'.$found;
                $relativePath = $relativePath.'/'.$found;
            }
            else
            {
                $error = true;
            }
        }

        if ($error)
        {
            return $this->render('SiteBundle:Default:debug.html.twig', array());
        }
        
        if (is_dir($currentPath))
        {
            $zip = $request->query->get('zip');
            
            if (!isset($zip))
            {
                
                // redirect if it ends with '/'
                if (strlen($uri) > 2 && substr($uri, -1) == '/')
                {
                    return $this->redirect(substr($uri, 0, strlen($uri)-1));
                }
                
                
                $dossier = DocUtils::getFullDossier($currentPath, $relativePath);
                
                // sort directories first
                usort($dossier->files, function ($a, $b) {
                    if ($a->isDir == $b->isDir)
                    {
                        return strnatcasecmp($a->name, $b->name) ;
                    }
                    if ($a->isDir && !$b->isDir)
                    {
                        return -1;
                    }
                    return 1;
                });
                
                
                return $this->render('SiteBundle:Default:dossier.html.twig', 
                        array(
                            "dossier" => $dossier,
                            "isRootDirectory" => ($uri=="/" || $uri == ""),
                            "rootDirectories" => $this->getRootDirectories()
                        ));
            }
            else
            {
                $dirToZip = $currentPath;
                $filename = tempnam("tmp", "zip");
                \SiteBundle\ZipUtils::createZipFolder($dirToZip, $filename);

                $response = new BinaryFileResponse($filename);
                
                register_shutdown_function('unlink', $filename);

                return $response;
            }
        }
        else
        {
            // send file
            $response = new BinaryFileResponse($currentPath);
            return $response;
           
        }
        
        return new Response("OOPS ".$uri);
        
    }

}
