<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WalletResource\Pages;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use PortalConnect\Wallet\WalletService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WalletResource extends Resource
{
    protected static ?string $model = Wallet::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Кошельки';
    protected static ?string $modelLabel = 'Кошелёк';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('user.email')->disabled()->label('Email'),
            Forms\Components\TextInput::make('balance_kopecks')->numeric()->disabled()->label('Баланс (коп.)'),
            Forms\Components\Toggle::make('auto_renew')->label('Автопродление'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user.email')->searchable()->label('Email'),
                Tables\Columns\TextColumn::make('balance_kopecks')
                    ->label('Баланс')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2, '.', ' ') . ' ₽')
                    ->sortable(),
                Tables\Columns\IconColumn::make('auto_renew')->boolean()->label('Auto-renew'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->label('Обновлён'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('auto_renew')->label('Автопродление'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('credit')
                    ->label('Начислить')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('amount_kopecks')->numeric()->required()->minValue(1)->label('Сумма (коп.)'),
                        Forms\Components\Textarea::make('description')->required()->label('Комментарий'),
                    ])
                    ->action(function (Wallet $record, array $data) {
                        app(WalletService::class)->credit(
                            $record,
                            (int) $data['amount_kopecks'],
                            WalletTransaction::TYPE_MANUAL_CREDIT,
                            [
                                'description' => $data['description'],
                                'created_by_admin_id' => auth()->id(),
                            ]
                        );
                        Notification::make()->title('Начисление выполнено')->success()->send();
                    }),
                Tables\Actions\Action::make('debit')
                    ->label('Списать')
                    ->icon('heroicon-o-minus-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\TextInput::make('amount_kopecks')->numeric()->required()->minValue(1)->label('Сумма (коп.)'),
                        Forms\Components\Textarea::make('description')->required()->label('Комментарий'),
                    ])
                    ->action(function (Wallet $record, array $data) {
                        try {
                            app(WalletService::class)->debit(
                                $record,
                                (int) $data['amount_kopecks'],
                                WalletTransaction::TYPE_MANUAL_DEBIT,
                                [
                                    'description' => $data['description'],
                                    'created_by_admin_id' => auth()->id(),
                                ]
                            );
                            Notification::make()->title('Списание выполнено')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Ошибка')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWallets::route('/'),
            'view' => Pages\ViewWallet::route('/{record}'),
        ];
    }
}
