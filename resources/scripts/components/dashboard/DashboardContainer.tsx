import React, { useEffect, useState } from 'react';
import { Server } from '@/api/server/getServer';
import getServers from '@/api/getServers';
import ServerRow from '@/components/dashboard/ServerRow';
import Spinner from '@/components/elements/Spinner';
import PageContentBlock from '@/components/elements/PageContentBlock';
import useFlash from '@/plugins/useFlash';
import { useStoreState } from 'easy-peasy';
import { usePersistedState } from '@/plugins/usePersistedState';
import Switch from '@/components/elements/Switch';
import tw from 'twin.macro';
import useSWR from 'swr';
import { PaginatedResult } from '@/api/http';
import Pagination from '@/components/elements/Pagination';

export default () => {
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const [ page, setPage ] = useState(1);
    const { rootAdmin } = useStoreState(state => state.user.data!);
    const [ includeAdmin, setIncludeAdmin ] = usePersistedState('show_all_servers', false);

    const { data: servers, error } = useSWR<PaginatedResult<Server>>(
        [ '/api/client/servers', includeAdmin, page ],
        () => getServers({ includeAdmin, page }),
    );

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'dashboard', error });
        if (!error) clearFlashes('dashboard');
    }, [ error ]);

    return (
        <PageContentBlock showFlashKey={'dashboard'}>
            {rootAdmin &&
            <div css={tw`mb-2 flex justify-end items-center`}>
                <p css={tw`uppercase text-xs text-neutral-400 mr-2`}>
                    {includeAdmin ? 'Showing all servers' : 'Showing your servers'}
                </p>
                <Switch
                    name={'show_all_servers'}
                    defaultChecked={includeAdmin}
                    onChange={() => setIncludeAdmin(s => !s)}
                />
            </div>
            }
            {!servers ?
                <Spinner centered size={'large'}/>
                :
                <Pagination data={servers} onPageSelect={setPage}>
                    {({ items }) => (
                        items.length > 0 ?
                            items.map((server, index) => (
                                <ServerRow
                                    key={server.uuid}
                                    server={server}
                                    css={index > 0 ? tw`mt-2` : undefined}
                                />
                            ))
                            :
                            <p css={tw`text-center text-sm text-neutral-400`}>
                                There are no servers associated with your account.
                            </p>
                    )}
                </Pagination>
            }
        </PageContentBlock>
    );
};
