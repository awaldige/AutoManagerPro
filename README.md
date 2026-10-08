# 🚗 AutoManager Pro — Sistema de Gestão de Concessionárias

O **AutoManager Pro** é um sistema web desenvolvido para auxiliar na gestão de concessionárias, centralizando informações sobre **veículos, estoque, lojas, vendas, fotos e relatórios** em uma única plataforma.

O projeto foi desenvolvido com **PHP, MySQL/MariaDB, JavaScript e Bootstrap**, com foco em organização dos dados, gerenciamento dos veículos e acompanhamento das operações comerciais.

---

## 🌐 Projeto Online

🔗 **Acesse a aplicação:**
[AutoManager Pro](https://automanagerpro.onrender.com/)

---

# ✨ Funcionalidades

## 📊 Dashboard

- Visualização dos principais indicadores do sistema
- Resumo das operações comerciais
- Informações relacionadas ao estoque e vendas
- 
## 🚘 Gestão de Veículos

- Cadastro de veículos
- Edição de informações
- Exclusão de registros
- Controle de modelos
- Organização dos veículos por loja
- Gerenciamento de fotos
  
## 🏢 Gestão de Lojas

- Cadastro e gerenciamento de lojas
- Associação de veículos às respectivas unidades
- Organização das informações por estabelecimento
  
## 📦 Controle de Estoque

- Visualização dos veículos disponíveis
- Controle dos veículos cadastrados
- Organização do estoque por loja
  
## 💰 Gestão de Vendas

- Registro de vendas
- Associação de veículos às vendas
- Consulta das operações realizadas
  
## 📈 Relatórios
- Consulta de informações comerciais
- Filtros por loja
- Filtros por período
- Visualização dos dados para acompanhamento das operações
  
## 🛠️ Tecnologias Utilizadas

- Tecnologia	Utilização
- PHP 8.x	Backend e regras de negócio
- MySQL / MariaDB	Banco de dados
- JavaScript	Interações da interface
- Bootstrap 5	Interface responsiva
- HTML5	Estrutura das páginas
- CSS3	Estilização
- SVG	Diagrama e elementos gráficos
  
## 🗄️ Banco de Dados

O projeto utiliza MySQL/MariaDB para armazenamento das informações do sistema.

O repositório contém o arquivo:

database.sql

Também está disponível um diagrama da estrutura do banco:

diagrama_er.svg

A estrutura foi organizada para representar entidades relacionadas à gestão de concessionárias, veículos, lojas e operações comerciais.

## 📊 Arquitetura de Dados
O sistema utiliza uma estrutura relacional sólida para garantir a integridade entre Marcas, Modelos, Lojas e Vendas.

![Diagrama de Entidade Relacionamento](diagrama_er.svg)



## 📂 Estrutura do Projeto
AutoManagerPro/
│
├── assets/
├── config/
├── controllers/
├── database.sql
├── diagrama_er.svg
├── includes/
├── models/
├── views/
├── index.php
└── README.md

A estrutura acima apresenta a organização geral do sistema. Os diretórios podem variar conforme a versão atual do projeto.

##  🔗 Acesse o projeto online:
http://awaldige.infinityfree.me/vendascarros/

## 📸 Prévia

![Captura de tela 2026-04-02 152136](https://github.com/user-attachments/assets/898dc5c2-bf8b-434d-a9c6-c0fd9b67ecbe)
![Captura de tela 2026-04-02 151943](https://github.com/user-attachments/assets/1737c6bb-5c19-4853-8cff-3dcd44df0f66)
![Captura de tela 2026-04-02 152312](https://github.com/user-attachments/assets/b963a66e-80ef-42f0-ae9c-71d776db1d79)

## 🚀 Como Executar Localmente
1. Clone o repositório
git clone https://github.com/awaldige/AutoManagerPro.git
2. Acesse a pasta
cd AutoManagerPro
3. Configure o servidor

Utilize um ambiente PHP local, como:

XAMPP
WAMP
Laragon

Coloque o projeto no diretório correspondente do servidor web.

4. Configure o banco de dados

Crie um banco de dados MySQL/MariaDB e importe o arquivo:

database.sql

5. Configure a conexão

Ajuste as credenciais de acesso ao banco de dados nos arquivos de configuração do projeto.

Exemplo:

Host: localhost
Database: automanager
User: root
Password: sua_senha
6. Execute o projeto

Com o Apache e o MySQL/MariaDB em execução, acesse o endereço local configurado pelo seu servidor.

Exemplo:

http://localhost/AutoManagerPro/

## 🧠 Destaques Técnicos

- Arquitetura web baseada em PHP
- Persistência de dados com MySQL/MariaDB
- Operações CRUD para gerenciamento dos dados
- Organização de veículos e estoque
- Gerenciamento de múltiplas lojas
- Registro e consulta de vendas
- Relatórios com filtros
- Interface responsiva utilizando Bootstrap
- Manipulação de dados utilizando JavaScript
- Diagrama de relacionamento do banco de dados

## 🔮 Possíveis Melhorias Futuras

- Sistema de autenticação e níveis de acesso mais avançados
- Histórico detalhado de alterações
- Exportação de relatórios
- Melhorias nos filtros e indicadores
- Notificações para operações importantes
- Integração com serviços externos
- Melhorias adicionais de desempenho e segurança
  
## 🎯 Objetivo do Projeto

O AutoManager Pro foi desenvolvido como uma solução de gestão para demonstrar na prática a aplicação de conceitos de desenvolvimento web Full Stack, banco de dados relacional, operações CRUD, organização de sistemas administrativos e desenvolvimento de interfaces responsivas.

## 👨‍💻 Autor

André Waldige

Desenvolvedor Full Stack — AW TECHNOLOGY

## 🔗 GitHub:
https://github.com/awaldige

## 📄 Licença

Projeto desenvolvido para portfólio profissional e demonstração de habilidades em desenvolvimento web.
