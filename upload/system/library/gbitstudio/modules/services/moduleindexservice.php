<?php
namespace Gbitstudio\Modules\Services;

use Gbitstudio\Modules\GdtConstants;
use Gbitstudio\Modules\Traits\JsonHandlerTrait;

class ModuleIndexService {
    use JsonHandlerTrait;

    private $index_file;

    public function __construct() {
        $this->index_file = GdtConstants::FILE_MODULE_INDEX;
    }

    /**
     * Get the full index of modules and their versions
     * @return array
     */
    public function getIndex(): array {
        if (!file_exists($this->index_file)) {
            return [];
        }

        $content = file_get_contents($this->index_file);
        
        return $this->decodeJson($content);
    }

    /**
     * Add or update a module in the index
     * @param string $code
     * @param string $version
     */
    public function updateIndex(string $code, string $version): void {
        $index = $this->getIndex();
        $index[$code] = $version;
        $this->saveIndex($index);
    }

    /**
     * Remove a module from the index
     * @param string $code
     */
    public function removeFromIndex(string $code): void {
        $index = $this->getIndex();
        if (isset($index[$code])) {
            unset($index[$code]);
            $this->saveIndex($index);
        }
    }

    /**
     * Save the index to file with locking
     * @param array $index
     */
    private function saveIndex(array $index): void {
        $content = $this->encodeJson($index);
        
        if (empty($content)) {
            return;
        }

        // Use atomic-like write with locking
        $temp_file = $this->index_file . '.tmp';
        file_put_contents($temp_file, $content, LOCK_EX);
        rename($temp_file, $this->index_file);
    }
}
