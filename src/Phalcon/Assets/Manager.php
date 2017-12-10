<?php
/**
 * Copyright (c) 2017 LKK/lianq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2017/12/10
 * Time: 15:30
 * Desc: -
 */


namespace Lkk\Phalwoo\Phalcon\Assets;

//use Phalcon\Tag;
use Lkk\Phalwoo\Phalcon\Tag;
use Phalcon\Assets\Resource;
use Phalcon\Assets\Collection;
use Phalcon\Assets\Exception;
use Phalcon\Assets\Resource\Js as ResourceJs;
use Phalcon\Assets\Resource\Css as ResourceCss;
use Phalcon\Assets\Inline\Css as InlineCss;
use Phalcon\Assets\Inline\Js as InlineJs;
use Phalcon\Assets\Inline;


/**
 * Phalcon\Assets\Manager
 *
 * Manages collections of CSS/Javascript assets
 */
class Manager {

    /**
     * Options configure
     * @var array
     */
    protected $_options;

    protected $_collections;

    protected $_implicitOutput = true;


    /**
     * Phalcon\Assets\Manager
     *
     * @param array $options
     */
    public function __construct($options = null) {
        if(is_object($options)) {
            $this->_options = $options;
        }
    }


    /**
     * Sets the manager options
     * @param array $options
     *
     * @return $this
     */
    public function setOptions(array $options) {
        $this->_options = $options;
        return $this;
    }


    /**
     * Returns the manager options
     * @return array|null
     */
    public function getOptions() {
        return $this->_options;
    }


    /**
     * Sets if the HTML generated must be directly printed or returned
     * @param bool $implicitOutput
     *
     * @return $this
     */
    public function useImplicitOutput(boolean $implicitOutput) {
        $this->_implicitOutput = $implicitOutput;
        return $this;
    }


    /**
     * Adds a Css resource to the 'css' collection
     *
     *<code>
     *	$assets->addCss("css/bootstrap.css");
     *	$assets->addCss("http://bootstrap.my-cdn.com/style.css", false);
     *</code>
     */
    public function addCss(string $path, $local = true, $filter = true, $attributes = null) {
        $this->addResourceByType("css", new ResourceCss($path, $local, $filter, $attributes));
        return $this;
    }


    /**
     * Adds an inline Css to the 'css' collection
     */
    public function addInlineCss(string $content, $filter = true, $attributes = null) {
        $this->addInlineCodeByType("css", new InlineCss($content, $filter, $attributes));
        return $this;
    }


    /**
     * Adds a javascript resource to the 'js' collection
     *
     *<code>
     * $assets->addJs("scripts/jquery.js");
     * $assets->addJs("http://jquery.my-cdn.com/jquery.js", false);
     *</code>
     */
    public function addJs(string $path, $local = true, $filter = true, $attributes = null) {
        $this->addResourceByType("js", new ResourceJs($path, $local, $filter, $attributes));
        return $this;
    }


    /**
     * Adds an inline javascript to the 'js' collection
     */
    public function addInlineJs(string $content, $filter = true, $attributes = null) {
        $this->addInlineCodeByType("js", new InlineJs($content, $filter, $attributes));
        return $this;
    }


    /**
     * Adds a resource by its type
     *
     *<code>
     * $assets->addResourceByType("css",
     *     new \Phalcon\Assets\Resource\Css("css/style.css")
     * );
     *</code>
     */
    public function addResourceByType(string $type, Resource $resource) {
        if(!isset($this->_collections[$type])) {
            $collection = new Collection();
            $this->_collections[$type] = $collection;
        }else{
            $collection = $this->_collections[$type];
        }

        /**
         * Add the resource to the collection
         */
        $collection->add($resource);

        return $this;
    }


    /**
     * Adds an inline code by its type
     */
    public function addInlineCodeByType(string $type, Inline $code) {
        if(!isset($this->_collections[$type])) {
            $collection = new Collection();
            $this->_collections[$type] = $collection;
        }else{
            $collection = $this->_collections[$type];
        }

        /**
         * Add the inline code to the collection
         */
        $collection->addInline($code);

        return $this;
    }


    /**
     * Adds a raw resource to the manager
     *
     *<code>
     * $assets->addResource(
     *     new Phalcon\Assets\Resource("css", "css/style.css")
     * );
     *</code>
     */
    public function addResource(Resource $resource) {
        /**
         * Adds the resource by its type
         */
        $this->addResourceByType($resource->getType(), $resource);
        return $this;
    }


    /**
     * Adds a raw inline code to the manager
     */
    public function addInlineCode(Inline $code) {
        /**
         * Adds the inline code by its type
         */
        $this->addInlineCodeByType($code->getType(), $code);
        return $this;
    }


    /**
     * Sets a collection in the Assets Manager
     *
     *<code>
     * $assets->set("js", $collection);
     *</code>
     */
    public function set(string $id, Collection $collection) {
        $this->_collections[$id] = $collection;
        return $this;
    }


    /**
     * Returns a collection by its id.
     *
     * <code>
     * $scripts = $assets->get("js");
     * </code>
     */
    public function get(string $id) {
        if(!isset($this->_collections[$id])) {
            throw new Exception("The collection does not exist in the manager");
        }else{
            $collection = $this->_collections[$id];
        }


        return $collection;
    }


    /**
     * Returns the CSS collection of assets
     */
    public function getCss() {
        /**
         * Check if the collection does not exist and create an implicit collection
         */
        if(isset($this->_collections["css"])) {
            $collection = $this->_collections["css"];
        }else{
            return new Collection();
        }

        return $collection;
    }


    /**
     * Returns the CSS collection of assets
     */
    public function getJs() {
        /**
         * Check if the collection does not exist and create an implicit collection
         */
        if(isset($this->_collections["js"])) {
            $collection = $this->_collections["js"];
        }else{
            return new Collection();
        }

        return $collection;
    }


    /**
     * Creates/Returns a collection of resources
     */
    public function collection(string $name) {
        if(!isset($this->_collections[$name])) {
            $collection = new Collection();
            $this->_collections[$name] = $collection;
        }else{
            $collection = $this->_collections[$name];
        }

        return $collection;
    }


    public function collectionResourcesByType(array $resources, string $type) {
        $filtered = [];
        $resource=null;

        foreach ($resources as $resource) {
            if($resource->getType()==$type) {
                $filtered[] = $resource;
            }
        }

        return $filtered;
    }


    /**
     * Traverses a collection calling the callback to generate its HTML
     *
     * @param \Phalcon\Assets\Collection collection
     * @param callback callback
     * @param string type
     */
    public function output(Collection $collection, $callback, $type) {
        $output = $resources = $filters = $prefix = $sourceBasePath = null;
        $targetBasePath = $options = $collectionSourcePath = $completeSourcePath =
        $collectionTargetPath = $completeTargetPath = $filteredJoinedContent = $join =
        $resource = $filterNeeded = $local = $sourcePath = $targetPath = $path = $prefixedPath =
        $attributes = $parameters = $html = $useImplicitOutput = $content = $mustFilter =
        $filter = $filteredContent = $typeCss = $targetUri = null;

        $useImplicitOutput = $this->_implicitOutput;
        $output = "";

        /**
         * Get the resources as an array
         */
        $resources = $this->collectionResourcesByType($collection->getResources(), $type);

        /**
         * Get filters in the collection
         */
        $filters = $collection->getFilters();

        /**
         * Get the collection's prefix
         */
        $prefix = $collection->getPrefix();

        $typeCss = "css";

        /**
         * Prepare options if the collection must be filtered
         */
        if(count($filters)>0) {
            $options = $this->_options;

            /**
             * Check for global options in the assets manager
             */
            if(is_array($options)) {
                /**
                 * The source base path is a global location where all resources are located
                 */
                $sourceBasePath = $options["sourceBasePath"];

                /**
                 * The target base path is a global location where all resources are written
                 */
                $targetBasePath = $options["targetBasePath"];
            }

            /**
             * Check if the collection have its own source base path
             */
            $collectionSourcePath = $collection->getSourcePath();

            /**
             * Concatenate the global base source path with the collection one
             */
            if ($collectionSourcePath) {
                $completeSourcePath = $sourceBasePath . $collectionSourcePath;
            } else {
                $completeSourcePath = $sourceBasePath;
            }

            /**
             * Check if the collection have its own target base path
             */
            $collectionTargetPath = $collection->getTargetPath();

            /**
             * Concatenate the global base source path with the collection one
             */
            if ($collectionTargetPath) {
                $completeTargetPath = $targetBasePath . $collectionTargetPath;
            } else {
                $completeTargetPath = $targetBasePath;
            }

            /**
             * Global filtered content
             */
            $filteredJoinedContent = "";

            /**
             * Check if the collection have its own target base path
             */
            $join = $collection->getJoin();

            /**
             * Check for valid target paths if the collection must be joined
             */
            if ($join) {
                /**
                 * We need a valid final target path
                 */
                if (!$completeTargetPath) {
                    throw new Exception("Path '". $completeTargetPath. "' is not a valid target path (1)");
                }

                if (is_dir($completeTargetPath)) {
                    throw new Exception("Path '". $completeTargetPath. "' is not a valid target path (2), is dir.");
                }
            }
        }//end coutn filters


        /**
         * walk in resources
         */
        foreach ($resources as $resource) {
            $filterNeeded = false;
            $type = $resource->getType();

            /**
             * Is the resource local?
             */
            $local = $resource->getLocal();

            /**
             * If the collection must not be joined we must print a HTML for each one
             */
            if(count($filters)>0) {
                if($local) {
                    /**
                     * Get the complete path
                     */
                    $sourcePath = $resource->getRealSourcePath($completeSourcePath);

                    /**
                     * We need a valid source path
                     */
                    if (!$sourcePath) {
                        $sourcePath = $resource->getPath();
                        throw new Exception("Resource '". $sourcePath. "' does not have a valid source path");
                    }
                }else{
                    /**
                     * Get the complete source path
                     */
                    $sourcePath = $resource->getPath();

                    /**
                     * resources paths are always filtered
                     */
                    $filterNeeded = true;
                }

                /**
                 * Get the target path, we need to write the filtered content to a file
                 */
                $targetPath = $resource->getRealTargetPath($completeTargetPath);

                /**
                 * We need a valid final target path
                 */
                if (!$targetPath) {
                    throw new Exception("Resource '". $sourcePath. "' does not have a valid target path");
                }

                if($local) {
                    /**
                     * Make sure the target path is not the same source path
                     */
                    if ($targetPath == $sourcePath) {
                        throw new Exception("Resource '". $targetPath. "' have the same source and target paths");
                    }

                    if (file_exists($targetPath)) {
                        if (compare_mtime($targetPath, $sourcePath)) {
                            $filterNeeded = true;
                        }
                    } else {
                        $filterNeeded = true;
                    }
                }
            }else{
                /**
                 * If there are not filters, just print/buffer the HTML
                 */
                $path = $resource->getRealTargetUri();

                if ($prefix) {
                    $prefixedPath = $prefix . $path;
                } else {
                    $prefixedPath = $path;
                }

                /**
                 * Gets extra HTML attributes in the resource
                 */
                $attributes = $resource->getAttributes();

                /**
                 * Prepare the parameters for the callback
                 */
                $parameters = [];
                if (is_array($attributes)) {
                    $attributes[0] = $prefixedPath;
                    $parameters[] = $attributes;
                } else {
                    $parameters[] = $prefixedPath;
                }
                $parameters[] = $local;

                /**
                 * Call the callback to generate the HTML
                 */
                $html = call_user_func_array($callback, $parameters);

                /**
                 * Implicit output prints the content directly
                 */
                if ($useImplicitOutput == true) {
                    echo $html;
                } else {
                    $output .= $html;
                }

                continue;
            }//count filters

            if($filterNeeded == true) {
                /**
                 * Gets the resource's content
                 */
                $content = $resource->getContent($completeSourcePath);

                /**
                 * Check if the resource must be filtered
                 */
                $mustFilter = $resource->getFilter();

                /**
                 * Only filter the resource if it's marked as 'filterable'
                 */
                if($mustFilter == true) {
                    foreach ($filters as $filter) {
                        if(!is_object($filter)) {
                            throw new Exception("Filter is invalid");
                        }

                        /**
                         * Calls the method 'filter' which must return a filtered version of the content
                         */
                        $content = $filteredContent = $filter->filter($content);
                    }

                    /**
                     * Update the joined filtered content
                     */
                    if($join == true) {
                        if($type == $typeCss) {
                            $filteredJoinedContent .= $filteredContent;
                        }else{
                            $filteredJoinedContent .= $filteredContent . ";";
                        }
                    }
                }else{
                    /**
                     * Update the joined filtered content
                     */
                    if ($join == true) {
                        $filteredJoinedContent .= $content;
                    } else {
                        $filteredContent = $content;
                    }
                }

                if($join) {
                    /**
                     * Write the file using file-put-contents. This respects the openbase-dir also
                     * writes to streams
                     */
                    file_put_contents($targetPath, $filteredContent);
                }
            }//endif filterNeeded

            if(!$join) {
                /**
                 * Generate the HTML using the original path in the resource
                 */
                $path = $resource->getRealTargetUri();

                if ($prefix) {
                    $prefixedPath = $prefix . $path;
                } else {
                    $prefixedPath = $path;
                }

                /**
                 * Gets extra HTML attributes in the resource
                 */
                $attributes = $resource->getAttributes();

                /**
                 * Filtered resources are always local
                 */
                $local = true;

                /**
                 * Prepare the parameters for the callback
                 */
                $parameters = [];
                if (is_array($attributes)) {
                    $attributes[0] = $prefixedPath;
                    $parameters[] = $attributes;
                } else {
                    $parameters[] = $prefixedPath;
                }
                $parameters[] = $local;

                /**
                 * Call the callback to generate the HTML
                 */
                $html = call_user_func_array($callback, $parameters);

                /**
                 * Implicit output prints the content directly
                 */
                if ($useImplicitOutput == true) {
                    echo $html;
                } else {
                    $output .= $html;
                }

            }//endif join

        }//end foreach resources


        if(count($filters)>0) {
            if($join == true) {
                /**
                 * Write the file using file_put_contents. This respects the openbase-dir also
                 * writes to streams
                 */
                file_put_contents($completeTargetPath, $filteredJoinedContent);

                /**
                 * Generate the HTML using the original path in the resource
                 */
                $targetUri = $collection->getTargetUri();

                if ($prefix) {
                    $prefixedPath = $prefix . $targetUri;
                } else {
                    $prefixedPath = $targetUri;
                }

                /**
                 * Gets extra HTML attributes in the collection
                 */
                $attributes = $collection->getAttributes();

                /**
                 *  Gets local
                 */
                $local = $collection->getTargetLocal();


                /**
                 * Prepare the parameters for the callback
                 */
                $parameters = [];
                if (is_array($attributes)) {
                    $attributes[0] = $prefixedPath;
                    $parameters[] = $attributes;
                } else {
                    $parameters[] = $prefixedPath;
                }
                $parameters[] = $local;

                /**
                 * Call the callback to generate the HTML
                 */
                $html = call_user_func_array($callback, $parameters);

                /**
                 * Implicit output prints the content directly
                 */
                if ($useImplicitOutput == true) {
                    echo $html;
                } else {
                    $output .= $html;
                }

            }

        }//endif filters

        return $output;
    }//end output



    /**
     * Traverses a collection and generate its HTML
     *
     * @param \Phalcon\Assets\Collection collection
     * @param string $type
     */
    public function outputInline(Collection $collection, $type) {
        $output = $html = $codes = $filters = $filter = $code = $attributes = $content = $join = $joinedContent = null;
        $output = $html = $joinedContent = '';

        $codes = $collection->getCodes();
        $filters = $collection->getFilters();
        $join = $collection->getJoin();

        if(count($codes)>0) {
            foreach ($codes as $code) {
                $attributes = $code->getAttributes();
                $content = $code->getContent();

                foreach ($filters as $filter) {
                    /**
                     * Filters must be valid objects
                     */
                    if (!is_object($filter)) {
                        throw new Exception("Filter is invalid");
                    }

                    /**
                     * Calls the method 'filter' which must return a filtered version of the content
                     */
                    $content = $filter->filter($content);
                }


                if ($join) {
                    $joinedContent .= $content;
                } else {
                    $html .= Tag::tagHtml($type, $attributes, false, true) . $content . Tag::tagHtmlClose($type, true);
                }
            }

            if ($join) {
                $html .= Tag::tagHtml($type, $attributes, false, true) . $joinedContent . Tag::tagHtmlClose($type, true);
            }

            /**
             * Implicit output prints the content directly
             */
            if ($this->_implicitOutput == true) {
                echo $html;
            } else {
                $output .= $html;
            }
        }

        return $output;
    }


    /**
     * Prints the HTML for CSS resources
     *
     * @param string $collectionName
     */
    public function outputCss($collectionName = null) {
        if(!$collectionName) {
            $collection = $this->getCss();
        }else{
            $collection = $this->get($collectionName);
        }

        //return $this->output($collection, ["Phalcon\\Tag", "stylesheetLink"], "css");
        return $this->output($collection, ["Lkk\\Phalwoo\\Phalcon\\Tag", "stylesheetLink"], "css");
    }


    /**
     * Prints the HTML for inline CSS
     *
     * @param string $collectionName
     */
    public function outputInlineCss($collectionName = null) {

        if (!$collectionName) {
            $collection = $this->getCss();
        } else {
            $collection = $this->get($collectionName);
        }

        return $this->outputInline($collection, "style");
    }


    /**
     * Prints the HTML for JS resources
     *
     * @param string $collectionName
     */
    public function outputJs($collectionName = null) {
        if (!$collectionName) {
            $collection = $this->getJs();
        } else {
            $collection = $this->get($collectionName);
        }

        return $this->output($collection, ["Lkk\\Phalwoo\\Phalcon\\Tag", "javascriptInclude"], "js");
    }


    /**
     * Prints the HTML for inline JS
     *
     * @param string $collectionName
     */
    public function outputInlineJs($collectionName = null) {
        if (!$collectionName) {
            $collection = $this->getJs();
        } else {
            $collection = $this->get($collectionName);
        }

        return $this->outputInline($collection, "script");
    }


    /**
     * Returns existing collections in the manager
     */
    public function getCollections() {
        return $this->_collections;
    }


    /**
     * Returns true or false if collection exists.
     *
     * <code>
     * if ($assets->exists("jsHeader")) {
     *     // \Phalcon\Assets\Collection
     *     $collection = $assets->get("jsHeader");
     * }
     * </code>
     */
    public function exists(string $id) {
        return isset($this->_collections[$id]);
    }




}

