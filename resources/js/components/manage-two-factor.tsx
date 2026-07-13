import { Form } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { disable, enable } from '@/routes/two-factor';

export type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

export default function ManageTwoFactor(props: Props) {
    const requiresConfirmation = props.requiresConfirmation ?? false;
    const twoFactorEnabled = props.twoFactorEnabled ?? false;

    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    if (!(props.canManageTwoFactor ?? false)) {
        return null;
    }

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="两步验证"
                description="管理两步验证设置"
            />
            {twoFactorEnabled ? (
                <div className="flex flex-col items-start justify-start space-y-4">
                    <p className="text-sm text-muted-foreground">
                        登录时会提示输入安全随机PIN码，该码可在TOTP应用中获取。
                    </p>

                    <div className="relative inline">
                        <Form {...disable.form()}>
                            {({ processing }) => (
                                <Button
                                    variant="destructive"
                                    type="submit"
                                    disabled={processing}
                                >
                                    关闭两步验证
                                </Button>
                            )}
                        </Form>
                    </div>

                    <TwoFactorRecoveryCodes
                        recoveryCodesList={recoveryCodesList}
                        fetchRecoveryCodes={fetchRecoveryCodes}
                        errors={errors}
                    />
                </div>
            ) : (
                <div className="flex flex-col items-start justify-start space-y-4">
                    <p className="text-sm text-muted-foreground">
                        启用两步验证后，登录时会提示输入安全PIN码。该PIN码可通过手机上的TOTP应用获取，比如 Google Authenticator 、Microsoft Authenticator、Authy、1Password、LastPass Authenticator等。
                    </p>

                    <div>
                        {hasSetupData ? (
                            <Button onClick={() => setShowSetupModal(true)}>
                                <ShieldCheck />
                                Continue setup
                            </Button>
                        ) : (
                            <Form
                                {...enable.form()}
                                onSuccess={() => setShowSetupModal(true)}
                            >
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        启用两步验证
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                </div>
            )}

            <TwoFactorSetupModal
                isOpen={showSetupModal}
                onClose={() => setShowSetupModal(false)}
                requiresConfirmation={requiresConfirmation}
                twoFactorEnabled={twoFactorEnabled}
                qrCodeSvg={qrCodeSvg}
                manualSetupKey={manualSetupKey}
                clearSetupData={clearSetupData}
                fetchSetupData={fetchSetupData}
                errors={errors}
            />
        </div>
    );
}
