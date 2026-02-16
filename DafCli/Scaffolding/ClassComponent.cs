using System;

namespace Daf.Scaffolding;

class ClassComponent : ProjectFile
{
    public ClassComponent(string fileName)
    {
        string name = fileName.Split('/').Last();
        string fileNamespace = fileName.Replace($"/{name}", string.Empty);
        FilePath = Path.Combine($"{fileName}.php".Split('/'));
        Namespace = fileNamespace.Replace('/', '\\');
        Content = $$"""
        <?php
        namespace {{Namespace}};
        use DafCore\Component;
        
        class {{name}}Component extends Component {
            public function OnLoad(): void { }

            // public function Render(): string { }
        }
        """;
    }
}
