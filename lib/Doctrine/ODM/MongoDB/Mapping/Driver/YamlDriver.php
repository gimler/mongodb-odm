<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ODM\MongoDB\Mapping\Driver;

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;

/**
 * The YamlDriver reads the mapping metadata from yaml schema files.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.org
 * @since       1.0
 * @version     $Revision$
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 */
class YamlDriver extends AbstractFileDriver
{
    /**
     * The file extension of mapping documents.
     *
     * @var string
     */
    protected $_fileExtension = '.dcm.yml';

    /**
     * {@inheritdoc}
     */
    public function loadMetadataForClass($className, ClassMetadata $class)
    {
        $element = $this->getElement($className);
        if ( ! $element) {
            return;
        }
        $element['type'] = isset($element['type']) ? $element['type'] : 'document';

        if (isset($element['db'])) {
            $class->setDB($element['db']);
        }
        if (isset($element['collection'])) {
            $class->setCollection($element['collection']);
        }
        if ($element['type'] == 'document') {
            if (isset($element['repositoryClass'])) {
                $class->setCustomRepositoryClass($element['repositoryClass']);
            }
        } elseif ($element['type'] === 'mappedSuperclass') {
            $class->isMappedSuperclass = true;
        } elseif ($element['type'] === 'embeddedDocument') {
            $class->isEmbeddedDocument = true;
        }
        if (isset($element['indexes'])) {
            foreach($element['indexes'] as $index) {
                $class->addIndex($index['keys'], $index['options']);
            }
        }
        if (isset($element['inheritanceType'])) {
            $class->setInheritanceType(constant('Doctrine\ODM\MongoDB\Mapping\ClassMetadata::INHERITANCE_TYPE_' . strtoupper($element['inheritanceType'])));
        }
        if (isset($element['discriminatorColumn'])) {
            $discrColumn = $element['discriminatorColumn'];
            $class->setDiscriminatorColumn(array(
                'name' => $discrColumn['name'],
                'fieldName' => $discrColumn['fieldName']
            ));
        }
        if (isset($element['discriminatorMap'])) {
            $class->setDiscriminatorMap($element['discriminatorMap']);
        }
        if (isset($element['fields'])) {
            foreach ($element['fields'] as $fieldName => $mapping) {
                if ( ! isset($mapping['fieldName'])) {
                    $mapping['fieldName'] = $fieldName;
                }
                $class->mapField($mapping);
            }
        }
        if (isset($element['embedOne'])) {
            foreach ($element['embedOne'] as $fieldName => $embed) {
                $mapping = $this->_getMappingFromEmbed($fieldName, $embed, 'one');
                $class->mapField($mapping);
            }
        }
        if (isset($element['embedMany'])) {
            foreach ($element['embedMany'] as $fieldName => $embed) {
                $mapping = $this->_getMappingFromEmbed($fieldName, $embed, 'many');
                $class->mapField($mapping);
            }
        }
        if (isset($element['referenceOne'])) {
            foreach ($element['referenceOne'] as $fieldName => $reference) {
                $mapping = $this->_getMappingFromReference($fieldName, $reference, 'one');
                $class->mapField($mapping);
            }
        }
        if (isset($element['referenceMany'])) {
            foreach ($element['referenceMany'] as $fieldName => $reference) {
                $mapping = $this->_getMappingFromReference($fieldName, $reference, 'many');
                $class->mapField($mapping);
            }
        }
        if (isset($element['lifecycleCallbacks'])) {
            foreach ($element['lifecycleCallbacks'] as $type => $methods) {
                foreach ($methods as $method) {
                    $class->addLifecycleCallback($method, constant('Doctrine\ORM\Events::' . $type));
                }
            }
        }
    }

    private function _getMappingFromEmbed($fieldName, $embed, $type)
    {
        $mapping = array(
            'name'           => $fieldName,
            'embedded'       => true,
            'type'           => $type,
            'targetDocument' => $embed['targetDocument'],
        );
        return $mapping;
    }

    private function _getMappingFromReference($fieldName, $reference, $type)
    {
        $mapping = array(
            'cascade'        => isset($reference['cascade']) ? $reference['cascade'] : null,
            'type'           => $type,
            'reference'      => true,
            'targetDocument' => $reference['targetDocument'],
            'name'           => $fieldName,
        );
        return $mapping;
    }

    protected function _loadMappingFile($file)
    {
        return \Symfony\Components\Yaml\Yaml::load($file);
    }
}