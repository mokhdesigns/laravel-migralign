<?php

namespace MigrAlign\ValueObjects;

enum ChangeOperation: string
{
    case AddColumn = 'add_column';
    case ModifyColumn = 'modify_column';
    case DropColumn = 'drop_column';
    case RenameColumn = 'rename_column';
    case CreateTable = 'create_table';
    case DropTable = 'drop_table';
}
