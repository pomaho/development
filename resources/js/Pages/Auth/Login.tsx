import { usePage } from '@inertiajs/react';
import GuestLayout from '../../Layouts/GuestLayout';

type Props = {
    links: {
        login: string;
        register: string;
    };
    registrationEnabled: boolean;
};

type PageProps = {
    errors?: Record<string, string>;
};

export default function Login({ links, registrationEnabled }: Props) {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const { props } = usePage<PageProps>();

    return (
        <GuestLayout title="Вход">
            <div className="mx-auto max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <form action={links.login} className="space-y-4" method="post">
                    <input name="_token" type="hidden" value={csrf} />
                    <label className="block text-sm">
                        <span>Email</span>
                        <input autoFocus className="mt-1 w-full rounded border-slate-300" name="email" required type="email" />
                        {props.errors?.email ? <div className="mt-1 text-xs text-red-700">{props.errors.email}</div> : null}
                    </label>
                    <label className="block text-sm">
                        <span>Пароль</span>
                        <input className="mt-1 w-full rounded border-slate-300" name="password" required type="password" />
                        {props.errors?.password ? <div className="mt-1 text-xs text-red-700">{props.errors.password}</div> : null}
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input className="rounded border-slate-300" name="remember" type="checkbox" />
                        <span>Запомнить</span>
                    </label>
                    <button className="w-full rounded bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800" type="submit">Войти</button>
                </form>
                {registrationEnabled ? (
                    <div className="mt-4 text-center text-sm">
                        <a className="text-blue-700 hover:text-blue-900" href={links.register}>Создать аккаунт</a>
                    </div>
                ) : null}
            </div>
        </GuestLayout>
    );
}
