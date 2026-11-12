# 🚗 AutoManagerPro — Sistema de Gestão de Concessionárias

O **AutoManagerPro** é um sistema web desenvolvido para auxiliar na administração de concessionárias de veículos, reunindo em uma única plataforma recursos para gerenciamento de estoque, lojas, veículos, clientes e vendas.

O projeto foi desenvolvido como aplicação demonstrativa para estudos, portfólio e apresentação de soluções de software da **AW TECHNOLOGY**.

## ✨ Funcionalidades

### 🚘 Gestão de veículos
- Cadastro de veículos.
- Edição e exclusão de registros.
- Organização por marca e modelo.
- Controle de informações como ano, preço e características.
- Exibição de fotos dos veículos.

### 🏢 Gestão de lojas
- Cadastro e gerenciamento de lojas.
- Edição de informações dos estabelecimentos.
- Associação de veículos às respectivas lojas.

### 💰 Gestão de vendas
- Registro de vendas de veículos.
- Associação de clientes, vendedores e veículos.
- Registro das informações comerciais da operação.
- Emissão de recibo demonstrativo de venda.

### 📊 Dashboard e relatórios
- Painel com indicadores de gestão.
- Consulta de informações do estoque.
- Relatórios de vendas.
- Acompanhamento das operações comerciais.

### ⚙️ Configurações
- Gerenciamento de marcas e modelos.
- Organização dos dados utilizados no cadastro de veículos.

## 🛠️ Tecnologias utilizadas

- **PHP 8.2** — processamento e regras de negócio.
- **MySQL / MariaDB** — armazenamento de dados.
- **MySQLi** — comunicação com o banco de dados.
- **Tailwind CSS** — estilização de interfaces.
- **Bootstrap** — componentes de interface em páginas específicas.
- **JavaScript** — interatividade.
- **HTML5** — estrutura das páginas.
- **Font Awesome** — ícones.
- **SVG** — elementos gráficos e favicon.

## 📁 Estrutura do projeto

```text
AutoManagerPro/
│
├── backups/                # Backups locais do banco de dados
├── uploads/                # Imagens dos veículos
│
├── index.php               # Dashboard principal
├── veiculos.php             # Listagem do estoque
├── cadastrar.php            # Cadastro de veículos
├── editar.php               # Edição de veículos
├── excluir.php              # Exclusão de veículos
├── vender.php               # Registro de vendas
├── relatorio_vendas.php     # Relatórios comerciais
├── gerenciar_lojas.php      # Gerenciamento de lojas
├── editar_loja.php          # Edição de lojas
├── configuracoes.php        # Marcas e modelos
├── imprimir_recibo.php      # Recibo demonstrativo
├── gerar_pdf.php            # Geração de PDF
├── conexao.php              # Conexão com o banco
│
├── database.sql             # Estrutura e dados iniciais do banco
├── diagrama_er.svg          # Diagrama entidade-relacionamento
├── favicon.svg              # Ícone do sistema
├── .gitignore               # Arquivos ignorados pelo Git
└── README.md                # Documentação
```

## 🗄️ Banco de dados

O projeto utiliza o banco de dados `vendascarros`, com estrutura relacional composta por 14 tabelas:

- `acessorio`
- `banco`
- `carro`
- `carro_acessorio`
- `cliente`
- `financeira`
- `gerente`
- `loja`
- `manutencao_garantia`
- `marca`
- `modelo`
- `tipopagamento`
- `venda`
- `vendedor`

O arquivo `database.sql` contém a estrutura necessária para a criação do banco.

O diagrama entidade-relacionamento está disponível em `diagrama_er.svg`.

## 🚀 Instalação local

### 1. Requisitos

- PHP 8.2 ou compatível.
- MySQL ou MariaDB.
- Apache ou servidor web equivalente.
- XAMPP (opcional, recomendado para ambiente local).

### 2. Clonar o repositório

```bash
git clone https://github.com/awaldige/AutoManagerPro.git
```

Entre na pasta:

```bash
cd AutoManagerPro
```

### 3. Configurar o banco de dados

Crie um banco chamado:

```sql
CREATE DATABASE vendascarros
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;
```

Importe o arquivo `database.sql` pelo phpMyAdmin ou pelo terminal MySQL.

### 4. Configurar a conexão

Abra o arquivo `conexao.php` e ajuste as configurações conforme seu ambiente:

```php
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "vendascarros";
$port = 3306;
```

**Importante:** a porta `3306` é apenas um exemplo. Utilize a porta configurada no seu MySQL ou MariaDB.

### 5. Executar o projeto

Coloque a pasta dentro do diretório público do Apache.

No XAMPP, normalmente:

```text
C:\xampp\htdocs\AutoManagerPro
```

Inicie o Apache e o MySQL/MariaDB pelo painel do XAMPP.

Acesse:

```text
http://localhost/AutoManagerPro/
```

## 🔒 Segurança e uso demonstrativo

Este projeto foi desenvolvido para fins demonstrativos, educacionais e de portfólio.

- Não utilize dados pessoais ou comerciais reais sem implementar as medidas de segurança necessárias.
- Configure credenciais próprias para o ambiente de execução.
- Não publique senhas ou informações confidenciais.
- Os backups locais do banco de dados não fazem parte do repositório.
- O recibo emitido pelo sistema é demonstrativo e não possui validade fiscal.

## 👨‍💻 Desenvolvedor

**André Waldige — AW TECHNOLOGY**

Desenvolvimento de sistemas web, aplicações personalizadas e soluções de automação para negócios.

- GitHub: https://github.com/awaldige
- Portfólio: https://andre-waldige.vercel.app/

---

*Desenvolvido por AW TECHNOLOGY.*